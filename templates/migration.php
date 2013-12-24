class <?= $name; ?> extends CDbMigration {
	/**
	 Dumb protection against auto import. It's still recommended to check this migration before using. Remove this method, if everything is ok.
	*/
	abstract public function foo();

	public function safeUp() {
		/**
		 * first of all we need to update the items and only after that we will update children
		 */

		$db = $this->getDbConnection();
<?
		foreach($log as $entry) {
			switch($entry[0]) {
				case self::OPERATION_INSERT:
?>

$db->createCommand()->insert('<?= $entry[1]; ?>', <? var_export($entry[2]); ?>);
<?
					break;
				case self::OPERATION_UPDATE:
?>

$db->createCommand()->update('<?= $entry[1]; ?>', <? var_export($entry[2]); ?>, '<?= $entry[3]; ?>', <? var_export($entry[4]); ?>);
<?
					break;
				case self::OPERATION_DELETE:
?>

$db->createCommand()->delete('<?= $entry[1]; ?>', '<?= $entry[2]; ?>', <? var_export($entry[3]); ?>);
<?
					break;
			}
		}
?>

		return true;
	}

	public function safeDown() {
		echo "{ClassName} does not support migration down.\\n";
		return false;
	}
}