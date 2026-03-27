-- Auctions & Raffles Schema
-- Run this once on the database to create the required tables.
--
-- Status conventions:
--   bids.status:    0 = outbid/superseded, 1 = leading, 2 = won
--   tickets.status: 0 = refunded, 1 = active

CREATE TABLE IF NOT EXISTS auctions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  winner_id    INT DEFAULT NULL,
  title        VARCHAR(255) NOT NULL,
  description  TEXT,
  image_path   VARCHAR(500) DEFAULT NULL,
  asset_id     VARCHAR(120) DEFAULT NULL,
  nft_name     VARCHAR(255) DEFAULT NULL,
  start_date   DATETIME NOT NULL,
  end_date     DATETIME NOT NULL,
  processing   TINYINT(1) NOT NULL DEFAULT 0,
  completed    TINYINT(1) NOT NULL DEFAULT 0,
  canceled     TINYINT(1) NOT NULL DEFAULT 0,
  created_date DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (winner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per accepted currency; minimum_bid is the floor in that currency.
CREATE TABLE IF NOT EXISTS auctions_projects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  auction_id  INT NOT NULL,
  project_id  INT NOT NULL,
  minimum_bid INT NOT NULL,
  FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- status: 0 = outbid, 1 = leading, 2 = won
CREATE TABLE IF NOT EXISTS bids (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  auction_id   INT NOT NULL,
  user_id      INT NOT NULL,
  amount       DECIMAL(15,2) NOT NULL,
  project_id   INT NOT NULL,
  status       INT NOT NULL DEFAULT 1,
  created_date DATETIME DEFAULT NOW(),
  FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raffles (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  winner_id         INT DEFAULT NULL,
  winning_ticket_id INT DEFAULT NULL,
  title             VARCHAR(255) NOT NULL,
  description       TEXT,
  image_path        VARCHAR(500) DEFAULT NULL,
  asset_id          VARCHAR(120) DEFAULT NULL,
  nft_name          VARCHAR(255) DEFAULT NULL,
  start_date        DATETIME NOT NULL,
  end_date          DATETIME NOT NULL,
  processing        TINYINT(1) NOT NULL DEFAULT 0,
  completed         TINYINT(1) NOT NULL DEFAULT 0,
  canceled          TINYINT(1) NOT NULL DEFAULT 0,
  created_date      DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (winner_id) REFERENCES users(id)
  -- winning_ticket_id FK added via ALTER TABLE below after tickets is created
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per accepted currency; cost is the per-ticket price in that currency.
CREATE TABLE IF NOT EXISTS raffles_projects (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  raffle_id  INT NOT NULL,
  project_id INT NOT NULL,
  cost       INT NOT NULL,
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- status: 0 = refunded, 1 = active
CREATE TABLE IF NOT EXISTS tickets (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  raffle_id    INT NOT NULL,
  project_id   INT NOT NULL,
  user_id      INT NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  status       INT NOT NULL DEFAULT 1,
  created_date DATETIME DEFAULT NOW(),
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add FK for winning_ticket_id after tickets table exists (avoids forward-reference error)
ALTER TABLE raffles
  ADD CONSTRAINT fk_raffles_winning_ticket
  FOREIGN KEY (winning_ticket_id) REFERENCES tickets(id);
