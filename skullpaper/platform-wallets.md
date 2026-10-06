# Wallets

Skulliance supports **multiple wallets** per account, across **four chains** - Cardano, Solana, Polygon and the XRP Ledger - so you can stake NFTs held anywhere under one profile.

## What connecting does, and what it does not

**Skulliance never asks you to sign or send anything.** Not to connect, not to stake, not to claim. Nothing leaves your wallet and no transaction is ever proposed to you.

Your wallet will still warn you, at connect time, that the site can "send requests for transactions" or words to that effect. **That is your wallet being honest, not Skulliance asking for it.** No major wallet offers a read-only connection: granting a site permission to see your address grants the ability to *propose* a transaction in the same breath, and there is no way to ask for one half. What that permission really means is that a site may put a transaction in front of you - which you would then have to approve yourself.

So Skulliance does the one thing it can: **it gives the permission straight back.** Once your address is recorded, the connection is handed back automatically and Skulliance disappears from your wallet's connected-sites list. Your wallet stays linked here, because that link lives in your Skulliance account and never needed your wallet again - from then on your holdings are read from the public blockchain, not from you.

Older wallet versions do not all support being disconnected this way. If Skulliance is still listed in yours, you can remove it yourself at any time and nothing here will stop working.

## Connecting Wallets

* Connect as many wallets as you like, on any mix of chains. Connecting a
  second wallet works exactly like the first - there is nothing to disconnect
  first.
* Pick the **chain** first, then the wallet. Cardano offers Lace, Eternl,
  Vespr and the rest; Solana offers Solflare, Phantom and Backpack; Polygon
  offers whichever EVM wallet you have set as your browser default, usually
  MetaMask; the XRP Ledger offers Xaman, Crossmark, GemWallet and Ledger.
* Use **Connect Another Wallet** on this page, or the wallet icon in the top
  navigation. Both open the same connect window.
* Your staked NFTs are aggregated across all connected wallets.
* Pick a wallet and approve the connection in that wallet's own popup. If the
  popup does not appear, the extension is usually locked or not enabled for this
  site; the connect window will tell you which wallet it is waiting on.
* **On Cardano** a wallet must be **delegated to a stake pool** before it can
  be connected - Skulliance identifies a Cardano wallet by its stake address.
  The other three chains have no equivalent requirement; the account is the
  address.
* **On Polygon**, your wallet may offer to switch networks when you connect.
  You can decline it. An Ethereum-style address is the same on every chain and
  Skulliance reads your Danketsu holdings from the Polygon network itself, so
  the switch is only there to reassure you that you are in the right place.
* Each wallet can belong to one Skulliance account. If a wallet is already
  connected somewhere else, you will be told rather than left guessing.
* Reconnecting a wallet you have already added is safe: it re-verifies your NFTs
  instead of adding a duplicate.

## Primary Wallet

You designate **one primary wallet**. This is the address Skulliance uses for delivery of anything you win or claim that settles on-chain - for example [[marketplace-auctions]] and [[marketplace-raffles]] prizes. You can change your primary wallet at any time.

Each wallet links out to a block explorer for its own chain - pool.pm, Solscan, PolygonScan or Bithomp - so you can verify the address and its holdings yourself.

## When new NFTs are counted

Linking a wallet counts what is in it **immediately** - you do not have to wait for the next day. After that, everything you hold is re-read **nightly**, so an NFT bought, sold or moved today is reflected by tomorrow. If a chain cannot be read on a given night, that chain's holdings are left exactly as they were rather than being guessed at, and the next night picks it up.
