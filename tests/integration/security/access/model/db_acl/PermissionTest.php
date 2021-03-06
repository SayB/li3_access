<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\tests\integration\security\access\model\db_acl;

use lithium\data\Connections;
use lithium\data\source\Database;
use li3_fixtures\test\Fixtures;
use li3_access\security\access\model\db_acl\Aco;
use li3_access\security\access\model\db_acl\Aro;
use li3_access\security\access\model\db_acl\Permission;

class PermissionTest extends \lithium\test\Integration {

	protected $_connection = 'test';

	protected $_models = [
		'_user' => 'li3_access\tests\fixture\model\blog\User',
		'_group' => 'li3_access\tests\fixture\model\blog\Group'
	];

	protected $_fixtures = [
		'user' => 'li3_access\tests\fixture\source\blog\UserFixture',
		'aco' => 'li3_access\tests\fixture\source\db_acl\AcoFixture',
		'aro' => 'li3_access\tests\fixture\source\db_acl\AroFixture',
		'permission' => 'li3_access\tests\fixture\source\db_acl\PermissionFixture'
	];

	protected $_privileges = ['create', 'read', 'update', 'delete'];

	/**
	 * Skip the test if no test database connection available.
	 */
	public function skip() {
		$dbConfig = Connections::get($this->_connection, ['config' => true]);
		$isAvailable = (
			$dbConfig &&
			Connections::get($this->_connection)->isConnected(['autoConnect' => true])
		);
		$this->skipIf(!$isAvailable, "No {$this->_connection} connection available.");

		$db = Connections::get($this->_connection);

		$this->skipIf(
			!($db instanceof Database),
			"The {$this->_connection} connection is not a relational database."
		);
	}

	public function setUp() {
		Fixtures::config([
			'db' => [
				'adapter' => 'Connection',
				'connection' => $this->_connection,
				'fixtures' => $this->_fixtures
			]
		]);
	}

	public function tearDown() {
		foreach($this->_models as $key => $class){
			$class::reset();
		}
		Fixtures::clear('db');
	}

	public function testAcl() {
		Fixtures::save('db');
		$result = Permission::acl('root/users/Peter', 'root/tpsReports/view/current');
		$expected = [
			'aro' => '9',
			'aco' => '4',
			'acl' => [
				'id' => '17',
				'aro_id' => '9',
				'aco_id' => '4',
				'privileges' => [
					'create' => '1',
					'read' => '1',
					'update' => '1',
					'delete' => '0'
		]]];
		$this->assertEqual($expected, $result);
	}

	public function testCheck() {
		Fixtures::save('db');

		$aro = 'root/users/Peter';
		$aco = 'root/tpsReports/view/current';
		$this->assertFalse(Permission::check($aro, $aco, $this->_privileges));
		$this->assertTrue(Permission::check($aro, $aco, 'create'));
		$this->assertTrue(Permission::check($aro, $aco, 'read'));
		$this->assertTrue(Permission::check($aro, $aco, 'update'));
		$this->assertFalse(Permission::check($aro, $aco, 'delete'));

		$aro = 'root/users/Samantha';
		$aco = 'root/printers/smash';
		$this->assertFalse(Permission::check($aro, $aco, $this->_privileges));
		$this->assertTrue(Permission::check($aro, $aco, 'create'));
		$this->assertTrue(Permission::check($aro, $aco, 'read'));
		$this->assertFalse(Permission::check($aro, $aco, 'update'));
		$this->assertTrue(Permission::check($aro, $aco, 'delete'));

		$this->assertTrue(Permission::check($aro, $aco, ['create', 'read', 'delete']));

		$this->assertTrue(Permission::check('Samantha', 'print', 'read'));
		$this->assertTrue(Permission::check('Lumbergh', 'current', 'read'));
		$this->assertFalse(Permission::check('Milton', 'smash', 'read'));
		$this->assertFalse(Permission::check('Milton', 'current', 'update'));

		$this->assertTrue(Permission::check('Bob', 'root/tpsReports/view/current', 'read'));
		$this->assertFalse(Permission::check('Samantha', 'root/tpsReports/update', 'read'));

		$this->assertFalse(Permission::check('root/users/Milton', 'smash', 'delete'));

		$this->assertFalse(Permission::check(null, 'root/tpsReports/view/current', 'read'));
		$this->assertFalse(Permission::check('Bob', null, 'read'));
		$this->assertFalse(Permission::check('Invalid', 'tpsReports', 'read'));

		$this->assertFalse(Permission::check('Lumbergh', 'smash', 'foobar'));
	}

	public function testCheckOnUnexistingNode() {
		Fixtures::save('db');

		$aro = 'root/users/Peter';
		$aco = 'root/tpsReports/view/current/unexisting_node';
		$this->assertFalse(Permission::check($aro, $aco, $this->_privileges));
		$this->assertTrue(Permission::check($aro, $aco, 'create'));
		$this->assertTrue(Permission::check($aro, $aco, 'read'));
		$this->assertTrue(Permission::check($aro, $aco, 'update'));
		$this->assertFalse(Permission::check($aro, $aco, 'delete'));
	}

	function testGet() {
		Fixtures::save('db');

		$aro = 'root/users/Samantha';
		$aco = 'root/printers/smash';
		$expected = ['create' => true, 'read' => true, 'update' => false, 'delete' => true];
		$result = Permission::get($aro, $aco, $this->_privileges);
		$this->assertEqual($expected, $result);

		$aro = 'root/users/Peter';
		$aco = 'root/tpsReports/view/current';
		$expected = ['create' => true, 'read' => true, 'update' => true, 'delete' => false];
		$result = Permission::get($aro, $aco, $this->_privileges);
		$this->assertEqual($expected, $result);
	}

	function testAliasAllow() {
		Fixtures::save('db');

		$this->assertFalse(Permission::check('Micheal', 'tpsReports', ['read']));
		$this->assertTrue(Permission::allow('Micheal', 'tpsReports', ['read', 'delete', 'update']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['update']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['read']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['delete']));
		$this->assertFalse(Permission::check('Micheal', 'tpsReports', ['create']));

		$this->assertTrue(Permission::allow('Micheal', 'root/tpsReports', ['create']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['create']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['delete']));
		$this->assertTrue(Permission::allow('Micheal', 'printers', ['create']));

		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['delete']));
		$this->assertTrue(Permission::check('Micheal', 'printers', ['create']));

		$this->assertFalse(Permission::check('root/users/Samantha', 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::allow('root/users/Samantha', 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::check('Samantha', 'view', 'read'));
		$this->assertTrue(Permission::check('root/users/Samantha', 'root/tpsReports/view', 'update'));

		$this->assertFalse(Permission::check('root/users/Samantha', 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::allow('root/users/Samantha', 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::check('Samantha', 'update', 'read'));
		$this->assertTrue(Permission::check('root/users/Samantha', 'root/tpsReports/update', ['update']));
		$this->assertTrue(Permission::check('root/users/Samantha', 'root/tpsReports/view', ['update']));

		$this->expectException('/Invalid acl node./');
		$this->assertFalse(Permission::allow('Lumbergh', 'root/tpsReports/DoesNotExist', 'create'));
	}

	function testArrayAllow() {
		Fixtures::save('db');
		extract($this->_models);
		$micheal = [
			'class' => $_user,
			'id' => 4
		];
		$this->assertFalse(Permission::check($micheal, 'tpsReports', ['read']));
		$this->assertTrue(Permission::allow($micheal, 'tpsReports', ['read', 'delete', 'update']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['update']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['read']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertFalse(Permission::check($micheal, 'tpsReports', ['create']));

		$this->assertTrue(Permission::allow($micheal, 'root/tpsReports', ['create']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['create']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertTrue(Permission::allow($micheal, 'printers', ['create']));

		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertTrue(Permission::check($micheal, 'printers', ['create']));

		$samantha = [
			'class' => $_user,
			'id' => 3
		];
		$this->assertFalse(Permission::check($samantha, 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::allow($samantha, 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::check($samantha, 'view', 'read'));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/view', 'update'));

		$this->assertFalse(Permission::check($samantha, 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::allow($samantha, 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::check($samantha, 'update', 'read'));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/update', ['update']));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/view', ['update']));

		$this->expectException('/Invalid acl node./');
		$this->assertFalse(Permission::allow('Lumbergh', 'root/tpsReports/DoesNotExist', 'create'));
		$_user::reset();
	}

	function testEntityAllow() {
		Fixtures::save('db');
		extract($this->_models);
		$micheal = $_user::create();
		$micheal->id = 4;

		$this->assertFalse(Permission::check($micheal, 'tpsReports', ['read']));
		$this->assertTrue(Permission::allow($micheal, 'tpsReports', ['read', 'delete', 'update']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['update']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['read']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertFalse(Permission::check($micheal, 'tpsReports', ['create']));

		$this->assertTrue(Permission::allow($micheal, 'root/tpsReports', ['create']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['create']));
		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertTrue(Permission::allow($micheal, 'printers', ['create']));

		$this->assertTrue(Permission::check($micheal, 'tpsReports', ['delete']));
		$this->assertTrue(Permission::check($micheal, 'printers', ['create']));

		$samantha = $_user::create();
		$samantha->id = 3;
		$this->assertFalse(Permission::check($samantha, 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::allow($samantha, 'root/tpsReports/view', $this->_privileges));
		$this->assertTrue(Permission::check($samantha, 'view', 'read'));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/view', 'update'));

		$this->assertFalse(Permission::check($samantha, 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::allow($samantha, 'root/tpsReports/update', $this->_privileges));
		$this->assertTrue(Permission::check($samantha, 'update', 'read'));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/update', ['update']));
		$this->assertTrue(Permission::check($samantha, 'root/tpsReports/view', ['update']));

		$this->expectException('/Invalid acl node./');
		$this->assertFalse(Permission::allow('Lumbergh', 'root/tpsReports/DoesNotExist', 'create'));
		$_user::reset();
	}

	function testInherit() {
		Fixtures::save('db');

		$this->assertFalse(Permission::check('Milton', 'smash', 'delete'));
		Permission::inherit('Milton', 'smash', 'delete');
		$this->assertFalse(Permission::check('Milton', 'smash', 'delete'));

		$this->assertFalse(Permission::check('Milton', 'smash', 'read'));
		Permission::inherit('Milton', 'smash', 'read');
		$this->assertTrue(Permission::check('Milton', 'smash', 'read'));
	}

	function testDeny() {
		Fixtures::save('db');

		$this->assertTrue(Permission::check('Micheal', 'smash', 'delete'));
		Permission::deny('Micheal', 'smash', 'delete');
		$this->assertFalse(Permission::check('Micheal', 'smash', 'delete'));
		$this->assertTrue(Permission::check('Micheal', 'smash', 'read'));
		$this->assertTrue(Permission::check('Micheal', 'smash', 'create'));
		$this->assertTrue(Permission::check('Micheal', 'smash', 'update'));
		$this->assertFalse(Permission::check('Micheal', 'smash', $this->_privileges));

		$this->assertTrue(Permission::check('Samantha', 'refill', $this->_privileges));
		Permission::deny('Samantha', 'refill', $this->_privileges);
		$this->assertFalse(Permission::check('Samantha', 'refill', 'create'));
		$this->assertFalse(Permission::check('Samantha', 'refill', 'update'));
		$this->assertFalse(Permission::check('Samantha', 'refill', 'read'));
		$this->assertFalse(Permission::check('Samantha', 'refill', 'delete'));

		$this->expectException('/Invalid acl node./');
		$this->assertFalse(Permission::deny('Lumbergh', 'root/tpsReports/DoesNotExist', 'create'));
	}

	/**
	 * Setup the acl permissions such that Bob inherits from admin.
	 * deny Admin delete access to a specific resource, check the permisssions are inherited.
	 */
	function testCascadingDeny() {
		Fixtures::save('db');

		Permission::inherit('Bob', 'root', $this->_privileges);
		$this->assertTrue(Permission::check('admin', 'tpsReports', 'delete'));
		$this->assertTrue(Permission::check('Bob', 'tpsReports', 'delete'));
		Permission::deny('admin', 'tpsReports', 'delete');
		$this->assertFalse(Permission::check('admin', 'tpsReports', 'delete'));
		$this->assertFalse(Permission::check('Bob', 'tpsReports', 'delete'));
	}

	public function testAllowOnTheFlyPrivilege() {
		Fixtures::save('db');

		$this->assertFalse(Permission::check('Micheal', 'tpsReports', ['publish']));
		$this->assertTrue(Permission::allow('Micheal', 'tpsReports', ['publish']));
		$this->assertTrue(Permission::check('Micheal', 'tpsReports', ['publish']));
		$this->assertTrue(Permission::check('Micheal', 'root/tpsReports', ['publish']));
		$this->assertTrue(Permission::check('Micheal', 'root/tpsReports/update', ['publish']));
	}

	/**
	 * debug function - to help editing/creating test cases for the ACL component
	 *
	 * To check the overall ACL status at any time call $this->__debug();
	 * Generates a list of the current aro and aco structures and a grid dump of the permissions that are defined
	 * Only designed to work with the db based ACL
	 *
	 * @param bool $treesToo
	 * @return void
	 */
	protected function _debug($printTreesToo = false) {
		Aro::meta('title', 'alias');
		Aco::meta('title', 'alias');
		$aros = Aro::find('list', ['order' => 'lft']);
		$acos = Aco::find('list', ['order' => 'lft']);
		$rights = [$this->_privileges, 'create', 'read', 'update', 'delete'];
		$permissions['Aros v Acos >'] = $acos;
		foreach ($aros as $aro) {
			$row = [];
			foreach ($acos as $aco) {
				$perms = '';
				foreach ($rights as $right) {
					if (Permission::check($aro, $aco, $right)) {
						if ($right == $this->_privileges) {
							$perms .= '****';
							break;
						}
						$perms .= $right[0];
					} elseif ($right != $this->_privileges) {
						$perms .= ' ';
					}
				}
				$row[] = $perms;
			}
			$permissions[$aro] = $row;
		}
		foreach ($permissions as $key => $values) {
			array_unshift($values, $key);
			$values = array_map([&$this, '_pad'], $values);
			$permissions[$key] = implode (' ', $values);
		}
		$permissions = array_map([&$this, '_pad'], $permissions);
		array_unshift($permissions, 'Current Permissions :');
		print_r(implode("\r\n", $permissions));
	}

	/**
	 * pad function
	 * Used by debug to format strings used in the data dump
	 *
	 * @param string $string
	 * @param integer $len
	 * @return void
	 */
	protected function _pad($string = '', $len = 14) {
		return str_pad($string, $len);
	}
}
