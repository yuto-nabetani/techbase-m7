-- =========================
-- ユーザーテーブル
-- =========================
CREATE TABLE IF NOT EXISTS users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- シーンテーブル
-- =========================
CREATE TABLE IF NOT EXISTS scenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    speaker VARCHAR(50),
    content_text TEXT NOT NULL,

    background_image VARCHAR(255),
    character_image VARCHAR(255),

    scene_type VARCHAR(20) NOT NULL DEFAULT 'normal',
    display_type VARCHAR(20) NOT NULL DEFAULT 'dialogue',
    next_scene_id INT NULL,

    FOREIGN KEY (next_scene_id)
        REFERENCES scenes(id)
);

-- =========================
-- チョイステーブル
-- =========================
CREATE TABLE IF NOT EXISTS choices(
    id INT AUTO_INCREMENT PRIMARY KEY,
    scene_id INT NOT NULL,
    choice_text VARCHAR(255) NOT NULL,
    love_change INT NOT NULL DEFAULT 0,
    next_scene_id INT NOT NULL,
    display_order INT NOT NULL DEFAULT 1,

    FOREIGN KEY (scene_id)
        REFERENCES scenes(id),
    
    FOREIGN KEY (next_scene_id)
        REFERENCES scenes(id)
);