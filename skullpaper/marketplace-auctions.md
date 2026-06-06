# Auctions

Auctions let members list NFTs and assets for bid-based sale, with bidding paid in Skulliance points.

## Creating an Auction

* Creating an auction requires **membership** (see [[staking-membership]]).
* A listing includes a title, description, the asset being sold, an image, and a start/end date.
* Auctions can accept **one or more project point types**, each with its own minimum opening bid - so bidders can compete using different points.
* A listing can be edited until the first bid is placed, and canceled by the creator until a winner is locked in.

## Bidding

* Bids are paid from your point balance and must **exceed the current high bid**.
* When you're outbid, your points are returned to you automatically.
* Bidding across different point types is normalized so auctions accepting multiple point types stay fair.

## After the Auction

When an auction ends, the highest bidder wins and the listing moves into delivery, where the winning NFT/asset is sent to the winner's primary wallet (see [[platform-wallets]]). Auction status moves through **upcoming → active → ended → processing → completed**.

Every bid and refund is logged in your [[platform-transactions]] ledger.
