# Soldiers

Soldiers are the NFTs you enlist into your Realm. They train in the Barracks, defend from the Tower, fight on raids, and can die and be resurrected. A strong army is what turns high location levels into actual offense and defense.

## Enlistment

Enlisting an NFT creates a soldier in your **reserve**. Newly enlisted soldiers are untrained and must complete Barracks training before they can be deployed to the Tower or sent on raids.

Each soldier occupies a number of **deployment slots**:

* **Core project NFTs** cost **1 slot**.
* **Partner project NFTs** cost **2 slots**.

Your total deployment capacity is set by your Barracks: **`min(100, Barracks level × 10)`**. Soldiers beyond your cap are held in reserve and can't be deployed until you raise the cap or remove others.

## Soldier Status

A soldier is always in one of these states:

* **Reserve** - trained (or training) and available, not currently deployed.
* **Tower** - stationed in the defensive garrison (max 10). Must be trained.
* **On Raid** - currently deployed on an active raid.
* **Dead** - killed on a raid; held until resurrected in the Crypt.

## Training

Training happens in the **Barracks** and takes **`(11 − Barracks level) × 24` hours**. A higher Barracks trains soldiers faster and raises your deployment cap. The **Fast Forward** consumable halves remaining training time.

## Gear

The **Armory** drops weapons and armor each night. Each soldier can equip **one weapon and one armor** piece. Gear comes in tiers (1–10); a higher-level Armory rolls from better tiers. Better-geared soldiers are prioritized and strengthen your army's contribution to offense and defense.

## Death & Resurrection

Soldiers can die during raids. Dead soldiers are held until your **Crypt** revives them, which takes **`(11 − Crypt level) × 24` hours** (Fast Forward halves it). Resurrected soldiers return to your reserve **with their gear intact**. A Level 0 Crypt cannot resurrect anyone, so keep it upgraded if you raid aggressively.

## Why Soldiers Matter

Your army directly feeds the raid formulas in [[realms-raids]]:

* Deployed, trained soldiers raise your **offense** (BarracksScore).
* Tower garrison raises your **defense** (TowerScore).

Two Realms with identical location levels can have very different raid outcomes based on how well their armies are trained, geared, deployed, and garrisoned.
