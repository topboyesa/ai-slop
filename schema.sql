-- Run this once in phpMyAdmin (or the mysql command line) to create the database.

CREATE DATABASE IF NOT EXISTS ai_slop
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_slop;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(30)  NOT NULL UNIQUE,
    email         VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed log-in attempts, used to slow down password guessing.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(254) NOT NULL,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views, likes and dislikes (added for the "sort" menu).
-- Safe to run again: existing tables and data are left alone.

-- How many times each article has been opened. post_id matches the article's id on the page (post-1, post-2, ...).
CREATE TABLE IF NOT EXISTS post_views (
    post_id VARCHAR(20)  NOT NULL PRIMARY KEY,
    views   INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One vote per member per article: 1 = like, -1 = dislike.
CREATE TABLE IF NOT EXISTS post_votes (
    user_id    INT UNSIGNED NOT NULL,
    post_id    VARCHAR(20)  NOT NULL,
    vote       TINYINT      NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    INDEX idx_post (post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments under each article. Members write them; the owner (and admins) can delete any.
CREATE TABLE IF NOT EXISTS comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    VARCHAR(20)  NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_time (post_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins can delete anyone's comments. Everyone else can only delete their own.
CREATE TABLE IF NOT EXISTS admins (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- To make yourself an admin, sign up on the site first, then run this
-- with your own username in place of yourusername:
-- INSERT IGNORE INTO admins (user_id) SELECT id FROM users WHERE username = 'yourusername';

-- Posts written from the site (write.php). Your three original posts stay in index.html;
-- new posts start at id 4 so they never clash with post-1, post-2 and post-3.
CREATE TABLE IF NOT EXISTS posts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
