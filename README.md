yii-rbac-migrate
================

Helper command to ease track of RBAC migration. This works only for AuthManager that uses database - CDbAuthManager or any other. Of course it depends on your way of changing RBAC roles - manually or via GUI. So in case of using GUI (like we are doing it), this helper will be very useful.

Usage:
======

1) at developer machine add next command at 'commandMap' of your config:
```php
'commandMap' => array(
		'rbacMigrate' => array(
			'class' => 'root.console.extensions.yii-rbac-migrate.rbacMigrateCommand',
		),
),
```

Command has alot of settings, so you can get info about it at source code. But by default, it must works fine with default CDbAuthManager.

2) run this command first time and it will create installation SQL that you will need to manually load into your developer's database.

3) from now any changes at RBAC core tables (itemTable, itemChildTable) will be logged.

4) when you will be ready to create migration, run this command again and it will create a new migration with all tracked changes.

5) revise that migration file and if everything is OK, then remove protection (abstract method 'foo').

6) run this new migration at production server, like you always do.

P.S.: Only core tables are tracking. Any assignments you will need to do manually - anyway that's bad idea auto-assign at production server. At least at such auto migration, better to create a new one migration where you will assign/revoke roles to/from users.

N.B.: This is BETA version.
