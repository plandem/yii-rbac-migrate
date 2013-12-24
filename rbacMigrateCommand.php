<?php
/**
 * @author Gayvoronsky Andrey <plandem@gmail.com>
 * @version 1.0
 *
 * Helper command to track RBAC changes at developer's machine to create full migration for production server.
 */
class rbacMigrateCommand extends CConsoleCommand {
	public $defaultAction = 'create';

	/**
	 * Type of operations at our log tables
	 */
	const OPERATION_INSERT = 1;
	const OPERATION_UPDATE = 2;
	const OPERATION_DELETE = 3;

	/**
	 * @var string the ID of the {@link CDbConnection} application component. Defaults to 'db'.
	 */
	public $connectionID = 'db';

	/**
	 * @var string the name of the table storing authorization items. Defaults to 'AuthItem'.
	 */
	public $itemTable = 'AuthItem';

	/**
	 * @var string the name of the table storing authorization item hierarchy. Defaults to 'AuthItemChild'.
	 */
	public $itemChildTable = 'AuthItemChild';

	/**
	 * @var string the name of AuthItem at the table storing authorization items. Defaults to 'name'.
	 */
	public $itemName = 'name';

	/**
	 * @var string the name of parent at the table storing item hierarchy. Defaults to 'parent'.
	 */
	public $itemParent = 'parent';

	/**
	 * @var string the name of child at the table storing item hierarchy. Defaults to 'child'.
	 */
	public $itemChild = 'child';

	/**
	 * @var string the path of the template file for generating new migrations. This
	 * must be specified in terms of a path alias (e.g. application.migrations.template).
	 * If not set, an internal template will be used.
	 */
	public $templateFile;

	/**
	 * @var string the directory that stores the migrations. This must be specified
	 * in terms of a path alias, and the corresponding directory must exist.
	 * Defaults to 'application.migrations' (meaning 'protected/migrations').
	 */
	public $migrationPath = 'application.migrations';

	/**
	 * @var boolean whether to execute the migration in an interactive mode. Defaults to true.
	 * Set this to false when performing migration in a cron job or background process.
	 */
	public $interactive = true;

	/**
	 * Prefix used for each table/trigger/function at this extension
	 * @var string
	 */
	public $trackPrefix = 'rbac_';

	/**
	 * @var CDbConnection the database connection. By default, this is initialized
	 * automatically as the application component whose ID is indicated as {@link connectionID}.
	 */
	public $db;

	/**
	 * @return CDbConnection the DB connection instance
	 * @throws CException if {@link connectionID} does not point to a valid application component.
	 */
	protected function getDbConnection() {
		if($this->db !== null)
			return $this->db;

		elseif(($this->db = Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection)
			return $this->db;

		throw new CException(Yii::t(get_class() . '.connectionID "{id}" is invalid. Please make sure it refers to the ID of a CDbConnection application component.',
			array('{id}' => $this->connectionID)));
	}

	public function getHelp() {
		return <<<EOD
USAGE
	yiic rbacMigrate create [name]

DESCRIPTION
	This command helps to track changes at RBAC core tables. It tries to reproduce something like "transaction log".

	This command knows nothing about assignments for users.
	We can't track it by many reason, especially when your data is distributed (RBAC core at central database and assignments at shards).
	That's your task to re-assign roles for users.

	N.B.:
	DON'T INSTALL this extension at production server. Purpose of this extension - help you to track RBAC changes at developing server and
	create migration to use at production server.
EOD;

	}

	public function beforeAction($action, $params) {
		$path = Yii::getPathOfAlias($this->migrationPath);

		if($path === false || !is_dir($path)) {
			echo 'Error: The migration directory does not exist: ' . $this->migrationPath . PHP_EOL;
			exit(1);
		}

		$this->migrationPath = $path;
		return parent::beforeAction($action, $params);
	}

	/**
	 * Generate an ew installation SQL file
	 * @throws CException
	 */
	public function prepareInstall() {
		if(strncmp($this->getDbConnection()->getDriverName(), 'mysql', 5))
			throw new CException('Unsupported DB driver');

		$driver = $this->getDbConnection()->getDriverName();

		$sql = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . "{$driver}.sql");

		$sql = strtr($sql, array(
			'{{itemTable}}' => $this->itemTable,
			'{{itemChildTable}}' => $this->itemChildTable,
			'{{itemName}}' => $this->itemName,
			'{{itemParent}}' => $this->itemParent,
			'{{itemChild}}' => $this->itemChild,
			'{{trackPrefix}}' => $this->trackPrefix,
		));

		$name = Yii::getPathOfAlias('root.console.runtime') . DIRECTORY_SEPARATOR . 'm' . gmdate('ymd_His') . '_' . 'rbacMigrate.sql';

		if(@file_put_contents($name, $sql)) {
			echo "You need to install SQL file for your settings manually. File saved as: {$name}" . PHP_EOL;
		} else {
			echo "Error during saving installation SQL file: {$name}" . PHP_EOL;
		}

		Yii::app()->end(1);
	}

	/**
	 * Checks if needed tables installed. Not checking needed triggers/operators.
	 * @return bool
	 */
	protected function isInstalled() {
		$installed = true;
		$installed &= ($this->getDbConnection()->getSchema()->getTable("{$this->trackPrefix}child_log") !== null);
		$installed &= ($this->getDbConnection()->getSchema()->getTable("{$this->trackPrefix}item_log") !== null);
		return $installed;
	}

	public function confirm($message, $default = false) {
		if(!$this->interactive)
			return true;

		return parent::confirm($message,$default);
	}

	/**
	 * Generate migration with changed RBAC items.
	 * @param $args
	 * @return int
	 */
	public function actionCreate($args) {
		if(!($this->isInstalled()))
			$this->prepareInstall();

		if(isset($args[0]))
			$name = $args[0];
		else
			$this->usageError('Please provide the name of the new migration.');

		if(!preg_match('/^\w+$/', $name)) {
			echo "Error: The name of the migration must contain letters, digits and/or underscore characters only." . PHP_EOL;
			return 1;
		}

		$name = 'm' . gmdate('ymd_His') . '_' . $name;
		$file = $this->migrationPath . DIRECTORY_SEPARATOR . $name . '.php';

		$template = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'migration.php';

		if($this->templateFile)
			$template = Yii::getPathOfAlias($this->templateFile) . '.php';

		if(!(file_exists($template))) {
			echo "Template file is not found: '{$template}'" . PHP_EOL;
			return 1;
		}

		if($this->confirm("Create new migration '$file'?")) {
			/**
			 * TODO: add pagination, sometimes it can be quite collection.
			 */
			$items = $this->getDbConnection()->createCommand()->select('*')->from("{$this->trackPrefix}item_log")->order('id')->queryAll();
			$children = $this->getDbConnection()->createCommand()->select('*')->from("{$this->trackPrefix}child_log")->order('id')->queryAll();

			$log = array();

			$db = $this->getDbConnection();

			try {
				/**
			 	* first of all we need to update the items
			 	*/
				foreach($items as $item) {
					switch($item['operation']) {
						case self::OPERATION_INSERT:
							/**
						 	* Is item still exists? N.B.: It can be renamed or deleted, so differ name.
						 	*/
							if(!($record = $db->createCommand()->select('*')->from($this->itemTable)->where("{$this->itemName} = :item", array(":item" => $item['name']))->queryRow()))
								continue;

							$log[] = array(self::OPERATION_INSERT, $this->itemTable, $record);
							break;
						case self::OPERATION_UPDATE:
							/**
						 	* Is item still exists? N.B.: It can be renamed or deleted, so differ name.
						 	*/
							if(!($record = $db->createCommand()->select('*')->from($this->itemTable)->where("{$this->itemName} = :item", array(":item" => $item['name']))->queryRow()))
								continue;

							$log[] = array(self::OPERATION_UPDATE, $this->itemTable, $record, "{$this->itemName} = :item", array(":item" => $item['name_old']));
							break;
						case self::OPERATION_DELETE:
							$log[] = array(self::OPERATION_DELETE, $this->itemTable, "{$this->itemName} = :item", array(":item" => $item['name']));
							break;
						default:
							throw new CException("Unknown type of operation at table '{$this->trackPrefix}item_log'.");
					}
				}

				/**
				 * now we can to update the children
				 */
				foreach($children as $item) {
					switch($item['operation']) {
						case self::OPERATION_INSERT:
							/**
						 	* Is child still exists? N.B.: It can be deleted.
						 	*/
							if(!($record = $db->createCommand()->select('*')->from($this->itemChildTable)->where("{$this->itemParent} = :parent AND {$this->itemChild} = :child", array(':parent' => $item['parent'], ':child' => $item['child']))->queryRow()))
								continue;

							$log[] = array(self::OPERATION_INSERT, $this->itemChildTable, array($this->itemParent => $item['parent'], $this->itemChild => $item['child']));
							break;
						case self::OPERATION_DELETE:
							$log[] = array(self::OPERATION_DELETE, $this->itemChildTable, "{$this->itemParent} = :parent AND {$this->itemChild} = :child", array(":parent" => $item['parent'], ":child" => $item['child']));
							break;
						default:
							throw new CException("Unknown type of operation at table '{$this->trackPrefix}child_log'.");
					}
				}
			} catch(CException $e) {
				echo $e->getMessage() . PHP_EOL;
				return 1;
			}

			/**
			 * Anything changed?
			 */
			if(empty($log)) {
				echo "There are no any changes at '{$this->itemTable}' and '{$this->itemChildTable}' tables." . PHP_EOL;
				return 0;
			}

			ob_start();
			include_once($template);
			$content = '<?php ' . ob_get_clean();
			file_put_contents($file, $content);
			echo "New migration created successfully." . PHP_EOL;

			/**
			 * Cleanup logs table
			 */
			$t = $db->beginTransaction();
			$db->createCommand()->delete("{$this->trackPrefix}item_log");
			$db->createCommand()->delete("{$this->trackPrefix}child_log");
			$t->commit();
			return 0;
		}
	}
}
