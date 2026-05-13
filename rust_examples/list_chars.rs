use sf_api::{gamestate::GameState, sso::SFAccount};
use std::env;

fn need_env(key: &str) -> String {
    env::var(key).unwrap_or_else(|_| {
        eprintln!("Missing env var: {key}");
        std::process::exit(2);
    })
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

    for (i, session) in sessions.iter_mut().enumerate() {
        let server_url = session.server_url().to_string();

        match session.login().await {
            Ok(login_res) => {
                match GameState::new(login_res) {
                    Ok(gs) => {
                        let name = gs.character.name.to_string();
                        let level = gs.character.level;
                        let guild = gs.guild.as_ref()
                            .map(|g| g.name.clone())
                            .unwrap_or_else(|| "Keine Gilde".to_string());

                        println!("Character #{}", i + 1);
                        println!("Server: {}", server_url);
                        println!("Name: {}", name);
                        println!("Level: {}", level);
                        println!("Guild: {}", guild);
                        println!();
                    }
                    Err(e) => {
                        eprintln!("GameState-Fehler für Charakter {}: {}", i + 1, e);
                    }
                }
            }
            Err(e) => {
                eprintln!("Login-Fehler für Charakter {}: {}", i + 1, e);
            }
        }
    }

    Ok(())
}
