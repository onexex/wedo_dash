# Import from projInventory

`tools/import-projinventory.php` copies the IT equipment register of the old
Laravel app **projInventory** (database `dbinventory`) into Inventory. Rules:
`includes/inventory-import-lib.php`. Tests: `tests/InventoryImportTest.php`.

## What comes over

| projInventory | Inventory |
|---|---|
| `tb_equipment` (one row per unit) | `inv_items`, tag `EQP-0001`… in the order of the old ids |
| `tb_eqpname` name | item name, title-cased (`IP CAMERA` → `IP Camera`) and category: Microwave Oven / Air Purifier / TV → **APP**, Docs → **DOC**, Key / Ladder / Multifunctional Whistle → **OTH**, everything else → **EQP** |
| `tb_brand` | Brand (text) |
| `tb_specsdsc` | Description, e.g. `Ram: 8GB; Processor: i5` |
| status Stock / Deployed | Status *In service*, condition *Good* |
| status Defective (or condition Defective) | Status *In service*, condition *Damaged* (open a repair from the item when needed) |
| status Disposed | Status *Disposed*, method *Other*, the old remarks as the disposal note |
| open deployment (`tb_eqpdeploy` status Deployed) | issued to that EmpID; acknowledged when its TSDR was issued |
| every deployment | *Issued* / *Returned* movements with the original dates and the IT user who recorded them |
| remarks | Notes, after `From projInventory #<old id>.` |

Every item is kept in one location, **IT Room** (created when missing). Items can
be moved from Inventory afterwards. Purchase data (`tb_assets`, `tb_supplier`) is
empty in projInventory, and the TSDR forms (`tbl_tsdr*`) have no Inventory
equivalent, so neither is imported.

## Run

1. The Inventory migration must already be on that database
   (`sql/2026-11-05-add-inventory.sql`, check with `sql/check-migrations.sql`).
2. Load the latest projInventory dump into a MySQL the machine can reach, as `dbinventory`.
3. Dry run. It saves nothing and prints the counts, the tags it would hand out and any warnings:

   ```bash
   php tools/import-projinventory.php --actor=<your EmpID>
   ```

4. Import:

   ```bash
   php tools/import-projinventory.php --actor=<your EmpID> --apply
   ```

The WeDo database is the one in `w_conn.php` (`config.local.php` or `DB_*` env vars).
The projInventory database defaults to `localhost`, `root`, no password, `dbinventory`.
Override it with `--src-host= --src-user= --src-pass= --src-db=` or `SRC_DB_HOST`,
`SRC_DB_USER`, `SRC_DB_PASS` and `SRC_DB_NAME`. Use `--location="Name"` for a different location.

Running it again is safe: an item whose serial number is already in Inventory is
skipped, so a later dump only adds the units created since. Changes made in
projInventory to items that were already imported (a new deployment, a disposal) are
**not** carried over. Stop using projInventory for equipment once the import is done.

Warnings name deployments that point to deleted equipment, and holders who are not
in `employees`. Those items come over as not issued.
