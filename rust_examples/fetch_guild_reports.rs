use chrono::{TimeZone, Utc};
use sf_api::{command::Command, gamestate::GameState, sso::SFAccount};
use std::{env, fs, io::{self, Write}, path::PathBuf, time::Duration};
use unidecode::unidecode;

fn need_env(key: &str) -> String {
    env::var(key).unwrap_or_else(|_| {
        eprintln!("Missing env var: {key}");
        std::process::exit(2);
    })
}

fn opt_env(key: &str) -> Option<String> {
    env::var(key).ok().filter(|v| !v.trim().is_empty())
}

fn sanitize_filename(s: &str) -> String {
    // Transliteriere zuerst: Пох Нах → Pokh Nakh
    let transliterated = unidecode(s);
    
    let mut out = String::new();
    for ch in transliterated.chars() {
        match ch {
            'a'..='z' | 'A'..='Z' | '0'..='9' | '-' | '_' => out.push(ch),
            ' ' => out.push('_'),
            _ => {}
        }
    }
    
    if out.is_empty() {
        "unknown".to_string()
    } else {
        out
    }
}

#[derive(Debug, Clone)]
struct SysMsg {
    id: u64,
    received: i64,
    _expires: i64,
    code: String,
}

fn parse_systemmessagelist(raw: &str) -> Vec<SysMsg> {
    raw.split(';')
        .filter(|s| !s.trim().is_empty())
        .filter_map(|entry| {
            let parts: Vec<&str> = entry.split(',').collect();
            if parts.len() < 7 {
                return None;
            }
            Some(SysMsg {
                id: parts[0].parse().ok()?,
                received: parts[4].parse().ok()?,
                _expires: parts[5].parse().ok()?,
                code: parts[6].to_string(),
            })
        })
        .collect()
}

fn date_de_utc(ts: i64) -> String {
    Utc.timestamp_opt(ts, 0)
        .single()
        .map(|dt| dt.format("%d.%m.%Y").to_string())
        .unwrap_or_else(|| "??.??.????".to_string())
}

fn time_utc(ts: i64) -> String {
    Utc.timestamp_opt(ts, 0)
        .single()
        .map(|dt| dt.format("%H:%M:%S").to_string())
        .unwrap_or_else(|| "??:??:??".to_string())
}

#[derive(Debug, Clone)]
struct PlayerLine {
    name: String,
    level: u32,
}

fn parse_report_messagetext(
    msg_text: &str,
) -> Option<(String, String, Vec<PlayerLine>, Vec<PlayerLine>)> {
    let tokens: Vec<&str> = msg_text.split('/').collect();
    if tokens.len() < 2 {
        return None;
    }

    let report_code = tokens[0].to_string();
    let opponent = tokens[1].to_string();

    let mut participated: Vec<PlayerLine> = Vec::new();
    let mut not_participated: Vec<PlayerLine> = Vec::new();

    let mut i = 2usize;
    while i + 4 < tokens.len() {
        let side: u8 = match tokens[i].parse() {
            Ok(v) => v,
            Err(_) => break,
        };

        let name = tokens[i + 2].to_string();
        let level: u32 = tokens[i + 3].parse().unwrap_or(0);

        let line = PlayerLine { name, level };
        if side == 1 {
            participated.push(line);
        } else {
            not_participated.push(line);
        }

        i += 5;
    }

    Some((report_code, opponent, participated, not_participated))
}

fn typ_label(code: &str) -> &'static str {
    match code {
        "2a" => "Angriff",
        "2d" => "Verteidigung",
        "3" => "Gildenraid",
        _ => "Unbekannt",
    }
}

#[derive(Debug, Clone)]
struct CharacterInfo {
    index: usize,
    name: String,
    server: String,
    guild: String,
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let sso_user = need_env("SSO_USERNAME");
    let sso_pass = env::var("SSO_PASSWORD")
        .or_else(|_| env::var("PASSWORD"))
        .unwrap_or_else(|_| {
            eprintln!("Missing env var: SSO_PASSWORD (or PASSWORD)");
            std::process::exit(2);
        });

    let out_dir = PathBuf::from(opt_env("OUT_DIR").unwrap_or_else(|| "./guildreports".to_string()));
    let since_epoch: Option<i64> = opt_env("SINCE_EPOCH").and_then(|v| v.parse().ok());
    let max_n: Option<usize> = opt_env("MAX").and_then(|v| v.parse().ok());

    fs::create_dir_all(&out_dir)?;

    println!("Logging in...");
    let account = SFAccount::login(sso_user, sso_pass).await?;
    
    let mut sessions: Vec<_> = account
        .characters()
        .await?
        .into_iter()
        .filter_map(|r| r.ok())
        .collect();

    if sessions.is_empty() {
        eprintln!("Keine Charaktere gefunden!");
        return Ok(());
    }

    // Charaktere auflisten mit Gildennamen
    println!("\n╔═══════════════════════════════════════════════════════════════════╗");
    println!("║  Verfügbare Charaktere:                                          ║");
    println!("╠═══════════════════════════════════════════════════════════════════╣");

    let mut char_infos = Vec::new();

    for (i, session) in sessions.iter_mut().enumerate() {
        match session.login().await {
            Ok(login_res) => {
                match GameState::new(login_res) {
                    Ok(gs) => {
                        let guild_name = gs.guild.as_ref()
                            .map(|g| g.name.clone())
                            .unwrap_or_else(|| "Keine Gilde".to_string());
                        
                        let server = session.server_url().host_str().unwrap_or("?").to_string();
                        let name = session.username().to_string();
                        
                        println!("║  [{}] {:<20} @ {:<20} │ Gilde: {:<15} ║",
                            i + 1,
                            name,
                            server,
                            guild_name
                        );
                        
                        char_infos.push(CharacterInfo {
                            index: i,
                            name,
                            server,
                            guild: guild_name,
                        });
                    }
                    Err(e) => {
                        eprintln!("  [{}] Fehler beim Laden: {:?}", i + 1, e);
                    }
                }
            }
            Err(e) => {
                eprintln!("  [{}] Login fehlgeschlagen: {:?}", i + 1, e);
            }
        }
    }

    println!("╚═══════════════════════════════════════════════════════════════════╝");

    if char_infos.is_empty() {
        eprintln!("\nKeine Charaktere mit Gilden gefunden!");
        return Ok(());
    }

    // Automatische Auswahl wenn ENV vars gesetzt
    let selected_char = if let (Some(target_server), Some(target_char)) = 
        (opt_env("SERVER_HOST"), opt_env("CHARACTER")) 
    {
        let mut found = None;
        for info in &char_infos {
            if info.server.contains(&target_server) && info.name == target_char {
                found = Some(info.clone());
                println!("\n✓ Automatisch gewählt: {} @ {} (Gilde: {})", 
                    info.name, info.server, info.guild);
                break;
            }
        }
        
        match found {
            Some(c) => c,
            None => {
                eprintln!("Charakter '{}' auf Server '{}' nicht gefunden!", target_char, target_server);
                return Ok(());
            }
        }
    } else {
        // Interaktive Character-Auswahl
        print!("\nWelchen Charakter verwenden? [1-{}]: ", char_infos.len());
        io::stdout().flush()?;

        let mut input = String::new();
        io::stdin().read_line(&mut input)?;

        let choice: usize = input.trim().parse().unwrap_or(0);

        if choice < 1 || choice > char_infos.len() {
            eprintln!("Ungültige Auswahl!");
            return Ok(());
        }

        char_infos[choice - 1].clone()
    };

    let mut session = sessions.remove(selected_char.index);

    println!("\n✓ Gewählt: {} @ {} (Gilde: {})\n",
        selected_char.name, selected_char.server, selected_char.guild);

    // Jetzt Berichte holen
    let login_res = session.login().await?;

    // systemmessagelist VORAB aus Login-Response extrahieren (Medea-Workaround:
    // Bei manchen Servern/Charakteren ist die Inbox leer, aber die
    // systemmessagelist ist trotzdem in der Login-Response enthalten)
    let login_syslist: Option<String> = login_res
        .values()
        .iter()
        .find(|(k, _)| k.starts_with("systemmessagelist"))
        .map(|(_, v)| v.as_str().to_string())
        .filter(|s| !s.trim().is_empty() && s.trim() != ";");

    let gs = GameState::new(login_res).expect("Failed to create gamestate");

    let raw_list = if let Some(ref list) = login_syslist {
        // systemmessagelist war schon in der Login-Response (funktioniert immer)
        println!("✓ systemmessagelist direkt aus Login-Response ({} bytes)", list.len());
        list.clone()
    } else {
        // Fallback: Seed-ID holen und PlayerMessageView senden
        let seed_id = if let Some(first_msg) = gs.mail.inbox.get(0) {
            first_msg.msg_id
        } else if let Some(first_claimable) = gs.mail.claimables.get(0) {
            eprintln!("⚠️  Inbox leer, verwende claimable msg_id {} als Seed", first_claimable.msg_id);
            first_claimable.msg_id as i32
        } else if let Some(guild_fight) = gs.mail.combat_log.iter()
            .find(|e| matches!(e.battle_type, sf_api::gamestate::social::CombatMessageType::GuildFight))
        {
            eprintln!("⚠️  Inbox und claimables leer, verwende GuildFight combat_log msg_id {} als Seed", guild_fight.msg_id);
            guild_fight.msg_id as i32
        } else if let Some(first_combat) = gs.mail.combat_log.get(0) {
            eprintln!("⚠️  Verwende ersten combat_log Eintrag als Seed (msg_id {})", first_combat.msg_id);
            first_combat.msg_id as i32
        } else {
            eprintln!("⚠️  Keine Nachrichten verfügbar für {} - überspringe Charakter", selected_char.name);
            return Ok(());
        };

        let seed_view = session
            .send_command(Command::Custom {
                cmd_name: "PlayerMessageView".to_string(),
                arguments: vec![seed_id.to_string()],
            })
            .await?;

        seed_view
            .values()
            .iter()
            .find(|(k, _)| k.starts_with("systemmessagelist"))
            .map(|(_, v)| v.as_str().to_string())
            .unwrap_or_default()
    };

    if raw_list.trim().is_empty() {
        eprintln!("systemmessagelist ist leer!");
        eprintln!("Tipp: Öffne einmal im Browser den Posteingang Tab 2 und starte erneut.");
        return Ok(());
    }

    println!("Hole Gildenkampfberichte...\n");

    let mut msgs = parse_systemmessagelist(&raw_list);
    msgs.retain(|m| m.code == "2a" || m.code == "2d" || m.code == "3");

    if let Some(since) = since_epoch {
        msgs.retain(|m| m.received >= since);
    }

    msgs.sort_by_key(|m| m.received);

    if let Some(limit) = max_n {
        if msgs.len() > limit {
            msgs = msgs.into_iter().rev().take(limit).collect();
            msgs.sort_by_key(|m| m.received);
        }
    }

    println!("Gefunden: {} Gildenkampfberichte (2a/2d/3)\n", msgs.len());

    // Guild-Verzeichnis erstellen (nur einmal)
    let guild_dir = out_dir.join(sanitize_filename(&selected_char.guild));
    fs::create_dir_all(&guild_dir)?;

    for m in msgs {
        let view = session
            .send_command(Command::Custom {
                cmd_name: "PlayerMessageView".to_string(),
                arguments: vec![m.id.to_string()],
            })
            .await?;

        let msg_text = view
            .values()
            .iter()
            .find(|(k, _)| k.starts_with("messagetext"))
            .map(|(_, v)| v.as_str())
            .unwrap_or("")
            .to_string();

        if msg_text.trim().is_empty() {
            eprintln!("⚠  msg_id={} returned empty messagetext, skipping", m.id);
            continue;
        }

        let (rc, opponent, participated, not_participated) =
            parse_report_messagetext(&msg_text)
                .unwrap_or((m.code.clone(), "Unbekannt".to_string(), vec![], vec![]));

        // Timestamp formatieren: YYYY-MM-DD_HH-MM
        let timestamp = Utc.timestamp_opt(m.received, 0)
            .single()
            .map(|dt| dt.format("%Y-%m-%d_%H-%M").to_string())
            .unwrap_or_else(|| format!("unknown_{}", m.received));

        // Gegner sanitizen
        let opponent_safe = sanitize_filename(&opponent);

        // Typ-Label
        let typ = typ_label(&rc);

        // Dateiname: YYYY-MM-DD_HH-MM_Typ_Gegner_msgID.txt
        let filename = if opponent_safe.is_empty() || opponent_safe == "unknown" {
            format!("{}_{}_unknown_msg{}.txt", timestamp, typ, m.id)
        } else {
            format!("{}_{}_{}_msg{}.txt", timestamp, typ, opponent_safe, m.id)
        };

        let out_path = guild_dir.join(&filename);

        if out_path.exists() {
            println!("⊘  {} (bereits vorhanden)", filename);
            continue;
        }

        // Header + Content
        let mut out = String::new();
        out.push_str("=== S&F KAMPFBERICHT ===\n");
        out.push_str(&format!("Gildenname: {}\n", selected_char.guild));
        out.push_str(&format!("Server: {}\n", selected_char.server));
        out.push_str(&format!("Charakter: {}\n", selected_char.name));
        out.push_str(&format!("Gegner: {}\n", opponent));
        out.push_str(&format!("Typ: {}\n", typ_label(&rc)));
        out.push_str(&format!("Datum: {}\n", date_de_utc(m.received)));
        out.push_str(&format!("Uhrzeit: {}\n", time_utc(m.received)));
        out.push_str(&format!("Message-ID: msg{}\n", m.id));
        out.push_str("=== ENDE HEADER ===\n\n");

        out.push_str("Mitglieder, die nicht teilgenommen haben:\n");
        for p in &not_participated {
            out.push_str(&format!("{} (Stufe {})\n", p.name, p.level));
        }

        out.push_str("\n");
        out.push_str("Mitglieder, die teilgenommen haben:\n");
        for p in &participated {
            out.push_str(&format!("{} (Stufe {})\n", p.name, p.level));
        }

        fs::write(&out_path, out)?;
        println!("✓  {}", filename);

        tokio::time::sleep(Duration::from_millis(1100)).await;
    }

    println!("\n✓ Fertig!");

    Ok(())
}