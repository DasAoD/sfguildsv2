#![allow(deprecated)]
//! guild_battle_time — Zeigt den exakten Zeitpunkt (mit Sekunden), an dem eine
//! beliebige Gilde angegriffen wird.
//!
//! JSON-Output für die Einbindung in sfguildsv2 (api/guild_attack_time.php) —
//! bei jedem Fehler wird trotzdem ein JSON-Objekt mit success:false
//! ausgegeben (kein reiner stderr-Text + exit-Code), damit der PHP-Aufrufer
//! immer sauber parsen kann. Idiom analog zu character_sync.rs.
//!
//! Die rohe ViewGuild-Antwort enthält noch ein zweites Zeitstempel/Namen-Paar
//! (Index 365 / "othergroupattack" in groupSave), das vermutlich den
//! *ausgehenden* Angriff der Zielgilde beschreibt — dessen Bedeutung wurde
//! nie gegen ein reales Beispiel verifiziert (nur das hier verwendete Paar
//! "wird angegriffen" wurde getestet), daher bewusst nicht mit ausgegeben.
//!
//! Umgebungsvariablen:
//!   SSO_USERNAME  — Pflicht
//!   SSO_PASSWORD  / PASSWORD — Pflicht
//!   SERVER_HOST   — z.B. f28.sfgame.net (Pflicht)
//!   CHARACTER     — Charaktername zum Einloggen (Pflicht)
//!   TARGET_GUILD  — Name der Ziel-Gilde, z.B. "Quitter" (Pflicht)

use sf_api::{command::Command, sso::SFAccount};
use serde::Serialize;
use std::env;

#[derive(Serialize)]
struct BattleTimeOutput {
    success: bool,
    target_guild: String,
    server: String,
    /// Unix-Timestamp (Sekunden, UTC), wann die Zielgilde angegriffen wird — None = kein Angriff geplant.
    attacked_at: Option<i64>,
    /// Name der angreifenden Gilde, falls bekannt.
    attacked_by: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    error: Option<String>,
}

fn parse_raw_kv(raw: &str) -> std::collections::HashMap<String, String> {
    let mut map = std::collections::HashMap::new();
    for part in raw.split('&') {
        if let Some((k, v)) = part.split_once(':') {
            map.insert(k.to_string(), v.to_string());
        }
    }
    map
}

#[tokio::main]
async fn main() {
    let result = run().await;
    println!("{}", serde_json::to_string(&result).unwrap_or_else(|_| {
        r#"{"success":false,"error":"JSON-Serialisierung fehlgeschlagen"}"#.to_string()
    }));
}

async fn run() -> BattleTimeOutput {
    let server_host = env::var("SERVER_HOST").unwrap_or_default();
    let target_guild = env::var("TARGET_GUILD").unwrap_or_default();

    let err_output = |msg: String, server: &str, target: &str| BattleTimeOutput {
        success: false,
        target_guild: target.to_string(),
        server: server.to_string(),
        attacked_at: None,
        attacked_by: None,
        error: Some(msg),
    };

    let sso_user = env::var("SSO_USERNAME").unwrap_or_default();
    if sso_user.is_empty() {
        return err_output("Fehlende Umgebungsvariable: SSO_USERNAME".to_string(), &server_host, &target_guild);
    }
    let sso_pass = env::var("SSO_PASSWORD").or_else(|_| env::var("PASSWORD")).unwrap_or_default();
    if sso_pass.is_empty() {
        return err_output("Fehlende SSO_PASSWORD/PASSWORD".to_string(), &server_host, &target_guild);
    }
    if server_host.is_empty() {
        return err_output("Fehlende Umgebungsvariable: SERVER_HOST".to_string(), &server_host, &target_guild);
    }
    let character = env::var("CHARACTER").unwrap_or_default();
    if character.is_empty() {
        return err_output("Fehlende Umgebungsvariable: CHARACTER".to_string(), &server_host, &target_guild);
    }
    if target_guild.is_empty() {
        return err_output("Fehlende Umgebungsvariable: TARGET_GUILD".to_string(), &server_host, &target_guild);
    }

    let account = match SFAccount::login(sso_user, sso_pass).await {
        Ok(a) => a,
        Err(e) => return err_output(format!("SSO Login fehlgeschlagen: {e}"), &server_host, &target_guild),
    };

    let mut sessions: Vec<_> = match account.characters().await {
        Ok(s) => s.into_iter().filter_map(|r| r.ok()).collect(),
        Err(e) => return err_output(format!("Charakterliste fehlgeschlagen: {e}"), &server_host, &target_guild),
    };

    let pos = match sessions.iter().position(|s| {
        s.server_url().host_str().unwrap_or("").contains(server_host.as_str())
            && s.username() == character.as_str()
    }) {
        Some(p) => p,
        None => {
            return err_output(
                format!("Charakter '{character}' auf '{server_host}' nicht gefunden"),
                &server_host,
                &target_guild,
            )
        }
    };
    let mut session = sessions.remove(pos);

    if let Err(e) = session.login().await {
        return err_output(format!("Login fehlgeschlagen: {e}"), &server_host, &target_guild);
    }

    let resp = match session
        .send_command_raw(&Command::ViewGuild { guild_ident: target_guild.clone() })
        .await
    {
        Ok(r) => r,
        Err(e) => return err_output(format!("ViewGuild fehlgeschlagen: {e}"), &server_host, &target_guild),
    };

    let kv = parse_raw_kv(resp.raw_response());

    let attacked_by = kv
        .get("othergroupdefense.r")
        .or_else(|| kv.get("othergroupdefense"))
        .cloned();

    // Index 367 = Verteidigungs-Timestamp der Zielgilde = wann sie angegriffen wird.
    let mut attacked_at: Option<i64> = None;
    if let Some(save) = kv.get("othergroup.groupSave").or_else(|| kv.get("othergroupsave")) {
        if let Some(v) = save.split('/').nth(367).and_then(|v| v.trim().parse::<i64>().ok()) {
            if v > 0 {
                attacked_at = Some(v);
            }
        }
    }

    BattleTimeOutput {
        success: true,
        target_guild,
        server: server_host,
        attacked_by: if attacked_at.is_some() { attacked_by } else { None },
        attacked_at,
        error: None,
    }
}
