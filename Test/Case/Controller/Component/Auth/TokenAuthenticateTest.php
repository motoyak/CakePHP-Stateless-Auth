<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Test.Case.Controller.Component.Auth
 * @since         CakePHP(tm) v 2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('TokenAuthenticate', 'StatelessAuth.Controller/Component/Auth');
App::uses('ComponentCollection', 'Controller');
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');

// test classes for mocking
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DS . "test_classes.php";

/**
 * Test case for FormAuthentication
 *
 * @package       Cake.Test.Case.Controller.Component.Auth
 */
class TokenAuthenticateTest extends CakeTestCase {

	/**
	 * Fixtures
	 *
	 * @var array
	 */
	public $fixtures = array(
		'plugin.stateless_auth.user',
		//'plugin.stateless_auth.permission',
		//'core.auth_user',
	);

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->Collection = $this->getMock('ComponentCollection');
		$this->auth = $this->getMock('TokenAuthenticate',
			array('generateToken'),
			array(
				$this->Collection,
				array(
					'fields' => array('username' => 'username', 'password' => 'password', 'token' => 'token'),
					'userModel' => 'StatelessAuthUser',
				),
			)
		);
		$this->auth->expects($this->any())
			->method('generateToken')
			->will($this->returnValue('abcde'));

		$password = Security::hash('password', null, true);
		$User = ClassRegistry::init('StatelessAuthUser');
		$User->updateAll(array('password' => $User->getDataSource()->value($password)));
		$this->response = $this->getMock('CakeResponse');
	}

	/**
	 * Test applying settings in the constructor.
	 *
	 * @return void
	 */
	public function testConstructor() {
		$settings = array(
			'userModel' => 'AuthUser',
			'fields' => array('username' => 'username', 'password' => 'password', 'token' => 'token'),
		);
		$Controller = new Controller();
		$this->Collection = new ComponentCollection($Controller);
		$object = new TokenAuthenticate($this->Collection, $settings);

		$this->assertEquals($settings['userModel'], $object->settings['userModel']);
		$this->assertEquals($settings['fields'], $object->settings['fields']);
	}

	/**
	 * Test authenticate success.
	 *
	 * @return void
	 */
	public function testAuthenticateSuccess() {
		$request = new CakeRequest('posts/index', false);

		// Set up a fake User model to return a dummy record.
		$expected = array(
			'User' => array(
				'id' => '7d5b22bd-fc92-11e3-b153-080027dec79b',
				'token' => 'abcde',
			),
		);

		$this->auth = $this->getMock('TokenAuthenticate',
			array('getUser'),
			array(new ComponentCollection(), array())
		);
		$this->auth->expects($this->once())
			->method('getUser')
			->with($request)
			->will($this->returnValue($expected));

		// Execute the SUT and check the direct returned result.
		$result = $this->auth->authenticate($request, $this->response);
		$this->assertEquals(
			$expected,
			$result,
			'authenticate() must pass back the User records provided by the User model.'
		);
	}

	/**
	 * Test _findUser() failure in authenticate().
	 *
	 * @return void
	 */
	public function testAuthenticateFindUserFails() {
		$request = new CakeRequest('posts/index', false);
		$request->data = array('User' => array(
			'username' => 'does-not-exist',
			'password' => 'test',
		));

		$this->assertFalse($this->auth->authenticate($request, $this->response));
	}

	/**
	 * Test that the logout method clears the token for the provided User.
	 *
	 * @return void
	 */
	public function testLogout() {
		$user = array(
			'id' => 'abcdef',
			'token' => null,
		);

		// Execute the SUT and check the direct returned result.
		$result = $this->auth->logout($user);
		$this->assertEquals(
			true,
			$result,
			'Logout on simply returns true.'
		);
	}

	/**
	 * Test the ability to check for a header and fetch the correct User record.
	 *
	 * @return void
	 */
	public function testGetUserSucceeds() {
		$token = 'foobar';
		$user = array(
			'User' => array(
				'id' => 'abcdef',
			),
		);
		$request = new CakeRequest();

		// Set up a fake User model to return a dummy record.
		$userModel = $this->getMockForModel('StatelessAuthUser');

		// Replace our accessor methods to return the dummy User model instance and dummy token.
		$this->auth = $this->getMock('TokenAuthenticate',
			array('getToken', 'findUserForToken'),
			array(new ComponentCollection(), array())
		);
		$this->auth->expects($this->once())
			->method('getToken')
			->with($request)
			->will($this->returnValue($token));
		$this->auth->expects($this->once())
			->method('findUserForToken')
			->with($token)
			->will($this->returnValue($user));

		// Execute the SUT and check the direct returned result.
		$result = $this->auth->getUser($request);
		$this->assertEquals(
			$user['User'],
			$result,
			'getUser() should return the expected User array.'
		);
	}

	/**
	 * Ensure getUser fails on a bad token.
	 *
	 * @return void
	 */
	public function testGetUserFails() {
		$token = 'foobar';
		$request = new CakeRequest();

		// Set up a fake User model to return a dummy record.
		$userModel = $this->getMockForModel('StatelessAuthUser');

		// Replace our accessor methods to return the dummy User model instance and dummy token.
		$this->auth = $this->getMock('TokenAuthenticate',
			array('getToken', 'findUserForToken'),
			array(new ComponentCollection(), array())
		);

		$this->auth->expects($this->once())
			->method('findUserForToken')
			->with($token)
			->will($this->returnValue(false));

		$this->auth->expects($this->once())
			->method('getToken')
			->with($request)
			->will($this->returnValue($token));

		// Execute the SUT and check the direct returned result.
		$this->expectException(
			'StatelessAuthUnauthorizedException',
			'Missing, invalid or expired token present in request. Include an Authorization header.'
		);
		$result = $this->auth->getUser($request);
	}

	/**
	 * Test that getModel fetches the expected object from the ClassRegistry.
	 *
	 * @return void
	 */
	public function testGetModel() {
		$this->auth->settings['userModel'] = 'TestCanary';
		$result = $this->auth->getModel();
		$this->assertEquals(
			'fizzbuzz',
			$result->property,
			'getModel() should return the `User` object from the classRegistry.'
		);
	}

	/**
	 * Test that getToken asks for the expected $_SERVER value and
	 * processes the result cleanly.
	 *
	 * @return void
	 */
	public function testGetToken() {
		$token = 'canary';
		$request = $this->getMock('CakeRequest', array('header'));
		$request->staticExpects($this->once())
			->method('header')
			->with('Authorization')
			->will($this->returnValue('Bearer ' . $token));

		$result = $this->auth->getToken($request);
		$this->assertEquals(
			'canary',
			$result,
			'getToken() should read the correct `$_SERVER` value and strip any leading `Bearer`.'
		);
	}
}
