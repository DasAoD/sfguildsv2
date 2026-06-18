//! guild_battle_info — Zeigt geplante Gildenkämpfe (Angriff + Verteidigung)
//!
//! Logt einen Charakter ein und gibt aus, ob ein Angriff oder eine
//! Verteidigung geplant ist, inklusive exaktem Zeitpunkt (mit Sekunden).
//!
//! Umgebungsvariablen:
//!   SSO_USERNAME  — Pflicht
//!   SSO_PASSWORD  / PASSWORD — Pflicht
//!   SERVER_HOST   — z.B. f25.sfgame.net (Pflicht)
//!   CHARACTER     — Charaktername (Pflicht)

use sf_api::{gamestate::GameState, sso::SFAccount};
use std::env;

fn need_env(key: &str) -> String {
    env::var(key).unwrap_or_else(|_| {
        eprintln!("Fehlende Umgebungsvariable: {key}");
        std::process::exit(2);
    })
}

fn format_dt(dt: &chrono::DateTime<chrono::Local>) -> String {
    dt.format("%d.%m.%Y  %H:%M:%S Uhr").to_string()
}

#[tokio::main]
async fn main() {
    let sso_user    = need_env("SSO_USERNAME");
    let sso_pass    = env::var("SSO_PASSWORD")
        .or_else(|_| env::var("PASSWORD"))
        .unwrap_or_else(|_| {
            eprintln!("Fehlende SSO_PASSWORD/PASSWORD");
            std::process::exit(2);
        });
    let server_host = need_env("SERVER_HOST");
    let character   = need_env("CHARACTER");

    // SSO-Login
    let account = match SFAccount::login(sso_user, sso_pass).await {
        Ok(a) => a,
        Err(e) => { eprintln!("SSO Login fehlgeschlagen: {e}"); std::process::exit(1); }
    };

    let mut sessions = match account.characters().await {
        Ok(s) => s.into_iter().filter_map(|r| r.ok()).collect::<Vec<_>>(),
        Err(e) => { eprintln!("Charakterliste fehlgeschlagen: {e}"); std::process::exit(1); }
    };

    let pos = match sessions.iter().position(|s| {
        s.server_url().host_str().unwrap_or("").contains(&server_host)
            && s.username() == character.as_str()
    }) {
        Some(p) => p,
        None => {
            eprintln!("Charakter '{character}' auf '{server_host}' nicht gefunden");
            std::process::exit(1);
        }
    };

    let mut session = sessions.remove(pos);

    let login_res = match session.login().await {
        Ok(r) => r,
        Err(e) => { eprintln!("Login fehlgeschlagen: {e}"); std::process::exit(1); }
    };

    let gs = match GameState::new(login_res) {
        Ok(g) => g,
        Err(e) => { eprintln!("GameState fehlgeschlagen: {e}"); std::process::exit(1); }
    };

    let guild = match &gs.guild {
        Some(g) => g,
        None => { eprintln!("Charakter ist in keiner Gilde"); std::process::exit(1); }
    };

    let guild_name  = &guild.name;
    let server      = session.server_url().host_str().unwrap_or("?").to_string();

    println!();
    println!("Gilde: {} ({})", guild_name, server);
    println!();

    match &guild.attacking {
        Some(b) => println!("⚔  Angriff:      {}  (Gegner-ID: {}{})",
            format_dt(&b.date),
            b.other,
            if b.is_raid() { "  [Raid]" } else { "" }),
        None => println!("⚔  Angriff:      –  (kein Angriff geplant)"),
    }

    match (&guild.attacking, &guild.defending) {
        (Some(atk), Some(def)) => {
            let diff_secs = (def.date - atk.date).num_seconds();
            let diff_str = if diff_secs >= 0 {
                format!("→ {}s nach unserem Angriff", diff_secs)
            } else {
                format!("→ {}s vor unserem Angriff", diff_secs.abs())
            };
            println!("🛡  Verteidigung: {}  (Gegner-ID: {})  {}",
                format_dt(&def.date), def.other, diff_str);
        }
        (None, Some(def)) => {
            println!("🛡  Verteidigung: {}  (Gegner-ID: {})",
                format_dt(&def.date), def.other);
        }
        _ => println!("🛡  Verteidigung: –  (kein Angriff auf uns geplant)"),
    }

    println!();
}
