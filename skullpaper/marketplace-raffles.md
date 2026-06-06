# Raffles

Raffles are a lottery-style alternative to auctions: buy numbered tickets with your points for a chance to win the prize NFT. The more tickets you hold, the better your odds.

## Creating a Raffle

* Creating a raffle requires **membership** (see [[staking-membership]]).
* A listing includes a title, description, the prize asset, an image, and a start/end date.
* The creator sets the **ticket cost per currency** (raffles can accept multiple currencies) and a **minimum tickets per purchase**.
* A listing can be edited until the first ticket sells, and canceled by the creator — which **refunds all ticket holders**.

## Buying Tickets

* Tickets are purchased in any quantity, with the cost deducted from your point balance.
* Holding more tickets increases your chance of being drawn as the winner.

## The Draw & Delivery

After the end date, a winner is drawn from all sold tickets and the listing moves into delivery, sending the prize to the winner's primary wallet (see [[platform-wallets]]). Status moves through **upcoming → active → ended → processing → completed**.

Every ticket purchase and refund is recorded in your [[platform-transactions]] ledger.
