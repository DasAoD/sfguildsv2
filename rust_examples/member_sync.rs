//! member_sync — Guild Member Sync für sfguildsv2
//!
//! Logt einen Charakter ein, liest alle Gildenmitglieder aus
//! und gibt die Daten als JSON auf stdout aus.
//!
//! Umgebungsvariablen:
//!   SSO_USERNAME  — Pflicht
//!   SSO_PASSWORD  / PASSWORD — Pflicht
//!   SERVER_HOST   — z.B. f25.sfgame.net (Pflicht)
//!   CHARACTER     — Charaktername (Pflicht)

use sf_api::{gamestate::GameState, sso::SFAccount};
use std::env;
use serde::Serialize;

fn need_env(key: &str) -> String {
    env::var(key).unwrap_or_else(|_| {
        let err = serde_json::json!({ "error": format!("Missing env var: {key}") });
        eprintln!("{}", err);
        std::process::exit(2);
    })
}

#[derive(Serialize)]
struct MemberOutput {
    name: String,
    level: u16,
    rank: String,
    last_online: Option<String>,
    gold: u16,
    mentor: u16,
    knight_hall: u8,
    guild_pet: u16,
}

#[derive(Serialize)]
struct SyncOutput {
    success: bool,
    guild_name: String,
    server: String,
    member_count: usize,
    members: Vec<MemberOutput>,
    #[serde(skip_serializing_if = "Option::is_none")]
    error: Option<String>,
}

#[tokio::main]
async fn main() {
    let result = run().await;
    println!("{}", serde_json::to_string(&result).unwrap_or_else(|_| {
        r#"{"success":false,"error":"JSON serialization failed"}"#.to_string()
    }));
}

async fn run() -> SyncOutput {
    let sso_user   = need_env("SSO_USERNAME");
    let sso_pass   = env::var("SSO_PASSWORD")
        .or_else(|_| env::var("PASSWORD"))
        .unwrap_or_else(|_| {
            eprintln!("Missing SSO_PASSWORD/PASSWORD");
            std::process::exit(2);
        });
    let server_host = need_env("SERVER_HOST");
    let character   = need_env("CHARACTER");

    let err_output = |msg: String| SyncOutput {
        success: false,
        guild_name: String::new(),
        server: server_host.clone(),
        member_count: 0,
        members: vec![],
        error: Some(msg),
    };

    // Login
    let account = match SFAccount::login(sso_user, sso_pass).await {
        Ok(a) => a,
        Err(e) => return err_output(format!("SSO Login fehlgeschlagen: {e}")),
    };

    let mut sessions = match account.characters().await {
        Ok(s) => s.into_iter().filter_map(|r| r.ok()).collect::<Vec<_>>(),
        Err(e) => return err_output(format!("Charakterliste fehlgeschlagen: {e}")),
    };

    let pos = match sessions.iter().position(|s| {
        s.server_url().host_str().unwrap_or("").contains(&server_host)
            && s.username() == character.as_str()
    }) {
        Some(p) => p,
        None => return err_output(format!("Charakter '{character}' auf '{server_host}' nicht gefunden")),
    };

    let mut session = sessions.remove(pos);

    let login_res = match session.login().await {
        Ok(r) => r,
        Err(e) => return err_output(format!("Login fehlgeschlagen: {e}")),
    };

    let gs = match GameState::new(login_res) {
        Ok(g) => g,
        Err(e) => return err_output(format!("GameState fehlgeschlagen: {e}")),
    };

    let guild = match &gs.guild {
        Some(g) => g,
        None => return err_output("Charakter ist in keiner Gilde".to_string()),
    };

    let guild_name = guild.name.clone();
    let server = session.server_url().host_str().unwrap_or("?").to_string();

    let members: Vec<MemberOutput> = guild.members.iter().map(|m| {
        let last_online = m.last_online.map(|dt| {
            dt.with_timezone(&chrono::Utc)
                .format("%Y-%m-%dT%H:%M:%SZ")
                .to_string()
        });

        let rank = match m.guild_rank {
            sf_api::gamestate::guild::GuildRank::Leader  => "Anführer",
            sf_api::gamestate::guild::GuildRank::Officer => "Offizier",
            sf_api::gamestate::guild::GuildRank::Member   => "Mitglied",
            sf_api::gamestate::guild::GuildRank::Invited  => "Eingeladen",
        }.to_string();

        MemberOutput {
            name:        m.name.to_string(),
            level:       m.level,
            rank,
            last_online,
            gold:        m.treasure_skill,
            mentor:      m.instructor_skill,
            knight_hall: m.knights,
            guild_pet:   m.guild_pet_lvl,
        }
    }).collect();

    SyncOutput {
        success: true,
        guild_name,
        server,
        member_count: members.len(),
        members,
        error: None,
    }
}
