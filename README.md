# SilverStripe DB Surgeon

** Warning: Experimental **

This module provides a task that will surgically migrate one SilverStripe database to another, and negotiate the differences, creating a new merged database.

## The Premise
If a migration task can be informed of a specific time at which the two databases were last identical, it can use a reliable set of heuristics to determine what records should be added, updated, or deleted on the recipient database, based on their `Created` and `LastEdited` timestamps.

## Assumptions
* The databases are on the same server, i.e. both accessible on `localhost`.
* The code is identical on both sites before the migration begins.
* A `dev/build` has been run on the target database, as the task only migrates data, not structure.

## The setup
The two databases are referred to as `source` and `target`. The *source* database is commonly a staging site, where new content is being developed. The *target* databse is commonly a production database, where the new content will be migrated without overwriting changes to new content. In your `_ss_environment.php` file, you must define credentials for the `source` database.

```php
define('SS_SOURCE_DATABASE_SERVER', 'localhost');
define('SS_SOURCE_DATABASE_USERNAME', 'username');
define('SS_SOURCE_DATABASE_PASSWORD','password');
define('SS_SOURCE_DATABASE_NAME', 'my_staging_db');
define('SS_SOURCE_DATABASE_TYPE','MySQLDatabase');
define('SS_SOURCE_URL', 'http://mystaging.example.com');
```

`SS_SOURCE_URL` is critical if you want to migrate uploaded files from the source to the target. This is currently done over HTTP, though a more robust solution like `rsync` might make more sense.

The `silverstripe-db-surgeon` module need only be installed on the *target* site.

## The process
When you're ready to start building new content, migrate the target database to the source (i.e. production to staging). At this point, the two databases should be identical. Before you begin modifying the source database, create a bookmark for the migration task.

Run the following command on the target site:
`framework/sake DatabaseSurgeonBookmarkTask`

This will create a hidden file in the `assets` folder called `.migration`. It contains a timestamp representing this bookmark.

Now, do your development on the source site, creating new code, database fields, and database content. During this time, it is okay to continue making content changes on the target site.

When you are ready to migrate, deploy any new code to prodcution, and run `dev/build?flush`. Then, run the migration task:

`framework/sake DatabaseSurgeonTask`

## How the migration works

There are three different migration tasks:
* **DataObjectMigration**: Migrates DataObject content (e.g. not SiteTree, not File)
* **SiteTreeMigration**: Migrates SiteTree content
* **AssetsMigration**: Migraties files and folders

Each migration task has three distinct phases:
* **update**: Find any records that need to be added or updated on the target database.
* **delete**: Find any records that are on the target database that are no longer on the source database, and delete them, provided they have not been modified since the bookmark.
* **relate**: Create `has_one` and `many_many` relations for any records that have been added to the target database, using their new IDs. `has_many` is omitted from this phase, as those relationships are just `has_one` at the database level.

## Migration heuristics
* If a record of the same `ClassName` and `ID` cannot be found on the target database, **CREATE**.

Otherwise, if a target record *can* be found with the same `ClassName` and `ID`:
* If both the target record and source record were created after the bookmark, keep both. **CREATE**.
* If both the target record and the source record have been edited since the bookmark, throw a **CONFLICT**.
* If the target record was edited since the bookmark, but the source record was not touched, **SKIP**.
* If the source record was edited since the bookmark, but the target record was not touched, **UPDATE**.

## Negotaiting conflicts

In the event of a **CONFLICT**, the user is prompted with three choices:
* (k)eep both
* keep (s)ource
* keep (b)oth

## Configuration
As experimental code at this phase, there is no configuration you can provide the task. Some obvious settings should include a list of classes and/or fields to include/exclude.


