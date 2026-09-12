# Customer Data Setup

## How to seed your 1001 customers:

1. **Create the file** `database/data/customers.sql`
2. **Paste your SQL** INSERT statements into that file (the exact SQL you shared with INSERT INTO `customers`...)
3. **Fix the INSERT syntax** — add the missing opening parenthesis after the table name:
   
   Change: `INSERT INTO `customers` `id`, ...`
   To: `INSERT INTO `customers` (`id`, ...`

4. **Run the seeder**:
   ```bash
   php artisan db:seed --class=CustomerSeeder
   ```
   
   Or for a full fresh start:
   ```bash
   php artisan migrate:fresh --seed
   ```

## What the seeder handles automatically:
- ✅ Converts `isPep` from `0`/`1` to `'no'`/`'yes'`
- ✅ Fixes invalid dates (`0000-00-00` → null)
- ✅ Fixes dates with invalid months (`2003-00-13` → null)  
- ✅ Inserts in batches of 100 for performance
- ✅ Works with SQLite and MySQL

## Alternative: Direct SQL import (MySQL only)
If you're using MySQL, you can import directly:
```bash
mysql -u root -p your_database < database/data/customers.sql
```
