-- Auctions & Raffles Schema
-- Run this once on the database to create the required tables.

CREATE TABLE IF NOT EXISTS auctions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  title            VARCHAR(255) NOT NULL,
  description      TEXT,
  image_path       VARCHAR(500) DEFAULT NULL,
  asset_id         VARCHAR(120) DEFAULT NULL,
  nft_name         VARCHAR(255) DEFAULT NULL,
  start_bid        DECIMAL(15,2) NOT NULL DEFAULT 0,
  bid_project_id   INT DEFAULT NULL,
  current_bid      DECIMAL(15,2) DEFAULT 0,
  current_bid_project_id INT DEFAULT NULL,
  current_bid_normalized DECIMAL(15,4) DEFAULT 0,
  current_bidder_id INT DEFAULT NULL,
  end_date         DATETIME NOT NULL,
  completed        TINYINT(1) NOT NULL DEFAULT 0,
  processing       TINYINT(1) NOT NULL DEFAULT 0,
  canceled         TINYINT(1) NOT NULL DEFAULT 0,
  created_date     DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (current_bidder_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auctions_projects (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  auction_id INT NOT NULL,
  project_id INT NOT NULL,
  FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bids (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  auction_id   INT NOT NULL,
  user_id      INT NOT NULL,
  amount       DECIMAL(15,2) NOT NULL,
  project_id   INT NOT NULL,
  normalized   DECIMAL(15,4) NOT NULL,
  created_date DATETIME DEFAULT NOW(),
  FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raffles (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NOT NULL,
  title              VARCHAR(255) NOT NULL,
  description        TEXT,
  image_path         VARCHAR(500) DEFAULT NULL,
  asset_id           VARCHAR(120) DEFAULT NULL,
  nft_name           VARCHAR(255) DEFAULT NULL,
  ticket_price       DECIMAL(15,2) NOT NULL DEFAULT 1,
  ticket_project_id  INT NOT NULL,
  max_tickets        INT DEFAULT NULL,
  tickets_per_user   INT DEFAULT NULL,
  end_date           DATETIME NOT NULL,
  winner_id          INT DEFAULT NULL,
  winning_ticket_id  INT DEFAULT NULL,
  completed          TINYINT(1) NOT NULL DEFAULT 0,
  processing         TINYINT(1) NOT NULL DEFAULT 0,
  canceled           TINYINT(1) NOT NULL DEFAULT 0,
  created_date       DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (winner_id) REFERENCES users(id),
  FOREIGN KEY (winning_ticket_id) REFERENCES tickets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raffles_projects (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  raffle_id  INT NOT NULL,
  project_id INT NOT NULL,
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tickets (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  raffle_id    INT NOT NULL,
  user_id      INT NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  project_id   INT NOT NULL,
  amount       DECIMAL(15,2) NOT NULL,
  created_date DATETIME DEFAULT NOW(),
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
