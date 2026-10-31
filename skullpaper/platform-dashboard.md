# Dashboard

The Dashboard is your staking home base - a view of all the NFTs you have staked and qualified across every project.

## What It Shows

* Your staked NFTs, displayed **27 per page** with prev/next pagination.
* **Filtering by project** so you can focus on one project at a time. With a project selected, a **collections dropdown** appears so you can narrow to a single collection instead of paging to find it. Both choices are carried in the link and survive pagination.
* Requires a connected wallet (see [[platform-wallets]]).

## Open an NFT to see what it is doing

Clicking an NFT no longer sends you off to a block explorer. It opens a panel about that one NFT: a larger view of the artwork, what it earns a night, and everything the platform currently has it doing.

* **On mission** and **missions done** - see [[missions]].
* **Gauntlet runs** - see [[missions-consumable-items]] and [[realms-raids]].
* **Raids fought**, with the record underneath: how many won, how many lost, how many attacking versus defending, and how many times it fell. A win is counted from your NFT's own side, so holding your realm against an attacker counts as a win for the defender.
* **Right now** - the missions it is on, the realm it is standing in and whether it is trained, whether it is away in a raid that has not resolved yet, and whether it is part of a Diamond Skull. The realm and the Diamond Skull are shown with their artwork, not just named.
* **Traits** - the asset's own on-chain metadata, listed in the order the mint wrote it. For a layered collection that is the order the art is built in, so the list reads the way the piece was assembled.
* Links out to the chain, the marketplace, the collection, and whichever parts of the platform it is actually involved in.

An NFT that is doing nothing says so plainly rather than showing a column of zeroes.

### Where the traits come from

The platform keeps each asset's metadata document as it was read off the
chain, so the trait list is not a Skulliance interpretation of your NFT - it
is what the mint says, with the picture and the project's social links left
out. **Raw metadata** under the list opens the document itself if you want to
see exactly that.

Two things can leave the list empty, and the panel says which:

* **"Its on-chain metadata carries no traits."** The document was read and
  there genuinely are no traits in it. Some collections mint nothing but a
  name and an image.
* **"Its on-chain metadata has not been read yet."** Nobody has fetched that
  asset's document so far. Cardano assets fill in whenever your wallets are
  verified; on XRPL, Solana and Polygon the document is only fetched for
  assets as they first appear, so already-staked ones are filled in by a
  separate pass.

## Saving the artwork

The panel says whether the artwork is **saved on Skulliance** or still loading from IPFS. A saved copy loads faster and survives a gateway going down.

The nightly job saves artwork automatically, but it does not always reach everything. When any NFT on the page you are looking at is still unsaved, a button appears that tries to save all of them, and reports what happened to each one - saved, already there, or why it failed. Only NFTs on the current page are attempted.

## Visibility

On your first visit you choose a **visibility setting** that controls whether your NFTs and activity appear publicly - for example on [[platform-leaderboards]] and the [[platform-gallery]]. You can keep your collection private or make it visible to the community.

Someone else's NFT panel opens only when that person has made their collection public. Your own always opens.

![Staked NFT dashboard](https://www.skulliance.io/staking/images/screenshots/dashboard.png)
