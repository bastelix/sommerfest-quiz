-- Create players table ahead of contact detail migration
CREATE TABLE IF NOT EXISTS players (
    event_uid TEXT NOT NULL,
    player_name TEXT NOT NULL,
    player_uid TEXT NOT NULL,
    PRIMARY KEY (event_uid, player_uid),
    CONSTRAINT fk_players_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
