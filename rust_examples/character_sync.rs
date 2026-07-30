//! character_sync — Attribut- und Ausrüstungs-Sync für sfguildsv2
//!
//! Loggt einen Charakter ein, liest alle Gildenmitglieder aus und ruft
//! pro Mitglied per ViewPlayer Attribute + Ausrüstung ab (gleiche Session,
//! kein erneuter Login pro Mitglied). Gibt die Rohdaten als JSON auf
//! stdout aus — Aufbereitung (Attribut-Summen, Ausrüstungsliste) passiert
//! PHP-seitig beim Speichern.
//!
//! Umgebungsvariablen:
//!   SSO_USERNAME   — Pflicht
//!   SSO_PASSWORD   / PASSWORD — Pflicht
//!   SERVER_HOST    — z.B. f25.sfgame.net (Pflicht)
//!   CHARACTER      — Charaktername (Pflicht)
//!   DELAY_MS       — Pause zwischen ViewPlayer-Calls (optional, default 700)
//!   TIME_BUDGET_S  — Hartes Zeitbudget für alle ViewPlayer-Calls, um die
//!                    ~2-Minuten-Session-Grenze nicht zu riskieren
//!                    (optional, default 90)

#![allow(deprecated)]

use sf_api::{command::Command, gamestate::GameState, sso::SFAccount};
use serde::Serialize;
use std::{env, time::{Duration, Instant}};

fn need_env(key: &str) -> String {
    env::var(key).unwrap_or_else(|_| {
        let err = serde_json::json!({ "success": false, "error": format!("Missing env var: {key}") });
        println!("{}", err);
        std::process::exit(2);
    })
}

fn opt_env(key: &str) -> Option<String> {
    env::var(key).ok().filter(|v| !v.trim().is_empty())
}

#[derive(Serialize)]
struct CharacterEntry {
    name: String,
    data: serde_json::Value,
}

#[derive(Serialize)]
struct SyncOutput {
    success: bool,
    guild_name: String,
    server: String,
    characters: Vec<CharacterEntry>,
    skipped: Vec<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    error: Option<String>,
}

#[tokio::main]
async fn main() {
    let result = run().await;
    println!("{}", serde_json::to_string(&result).unwrap_or_else(|_| {
        r#"{"success":false,"error":"JSON-Serialisierung fehlgeschlagen"}"#.to_string()
    }));
}

async fn run() -> SyncOutput {
    let sso_user = need_env("SSO_USERNAME");
    let sso_pass = env::var("SSO_PASSWORD")
        .or_else(|_| env::var("PASSWORD"))
        .unwrap_or_else(|_| {
            let err = serde_json::json!({ "success": false, "error": "Missing SSO_PASSWORD/PASSWORD" });
            println!("{}", err);
            std::process::exit(2);
        });
    let server_host = need_env("SERVER_HOST");
    let character = need_env("CHARACTER");
    let delay_ms: u64 = opt_env("DELAY_MS").and_then(|v| v.parse().ok()).unwrap_or(700);
    let time_budget_s: u64 = opt_env("TIME_BUDGET_S").and_then(|v| v.parse().ok()).unwrap_or(90);

    let err_output = |msg: String, server: &str| SyncOutput {
        success: false,
        guild_name: String::new(),
        server: server.to_string(),
        characters: vec![],
        skipped: vec![],
        error: Some(msg),
    };

    let account = match SFAccount::login(sso_user, sso_pass).await {
        Ok(a) => a,
        Err(e) => return err_output(format!("SSO Login fehlgeschlagen: {e}"), &server_host),
    };

    let mut sessions: Vec<_> = match account.characters().await {
        Ok(s) => s.into_iter().filter_map(|r| r.ok()).collect(),
        Err(e) => return err_output(format!("Charakterliste fehlgeschlagen: {e}"), &server_host),
    };

    let pos = match sessions.iter().position(|s| {
        s.server_url().host_str().unwrap_or("").contains(server_host.as_str())
            && s.username() == character.as_str()
    }) {
        Some(p) => p,
        None => {
            return err_output(
                format!("Charakter '{character}' auf '{server_host}' nicht gefunden!"),
                &server_host,
            )
        }
    };
    let mut session = sessions.remove(pos);

    let login_res = match session.login().await {
        Ok(r) => r,
        Err(e) => return err_output(format!("Login fehlgeschlagen: {e}"), &server_host),
    };
    let mut gs = match GameState::new(login_res) {
        Ok(g) => g,
        Err(e) => return err_output(format!("GameState fehlgeschlagen: {e}"), &server_host),
    };

    let guild = match gs.guild.clone() {
        Some(g) => g,
        None => return err_output("Charakter ist in keiner Gilde".to_string(), &server_host),
    };
    let guild_name = guild.name.clone();
    let server = session.server_url().host_str().unwrap_or("?").to_string();
    let member_names: Vec<String> = guild.members.iter().map(|m| m.name.clone()).collect();

    let start = Instant::now();
    let budget = Duration::from_secs(time_budget_s);
    let mut characters = Vec::new();
    let mut skipped = Vec::new();

    for (i, name) in member_names.iter().enumerate() {
        if start.elapsed() > budget {
            skipped.push(name.clone());
            continue;
        }
        if i > 0 {
            tokio::time::sleep(Duration::from_millis(delay_ms)).await;
        }

        let resp = session
            .send_command(Command::ViewPlayer { ident: name.clone() })
            .await;

        match resp {
            Ok(response) => {
                if gs.update(response).is_ok() {
                    match gs.lookup.lookup_name(name) {
                        Some(op) => match serde_json::to_value(op) {
                            Ok(v) => characters.push(CharacterEntry { name: name.clone(), data: v }),
                            Err(_) => skipped.push(name.clone()),
                        },
                        // Der eigene eingeloggte Account taucht nicht im Lookup-Cache auf.
                        None => skipped.push(name.clone()),
                    }
                } else {
                    skipped.push(name.clone());
                }
            }
            Err(_) => skipped.push(name.clone()),
        }
    }

    SyncOutput {
        success: true,
        guild_name,
        server,
        characters,
        skipped,
        error: None,
    }
}
