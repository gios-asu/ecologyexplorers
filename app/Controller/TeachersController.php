<?php
App::uses('AppController', 'Controller');
App::uses('Utitlity','Security');

/**
 * Teachers Controller
 *
 * @property Teacher $Teacher
*/
class TeachersController extends AppController {
	public $helpers = array('Html', 'Form','CSV');
	/**
	 * index method
	 *
	 * @return void
	*/
	public function index()
	{
		$this->Teacher->recursive = 0;
		$this->set('teachers', $this->paginate());
	}


	//Method to validate the user credentials before login.
	public function login($fields = null)
	{
		if($this->Session->check('User'))
		{
			$this->Session->setFlash('You have already logged in. Please logout.');
			$this->redirect(array(
					'action' => 'index'));

		}

		if ($this->request->is('post'))
		{

			$user = $this->Teacher->validateLogin($this->request->data['Teacher']['email_address'],$this->request->data['Teacher']['password']);
			if ($user)
			{
				if("pending" == $user)
				{
					$this->Session->setFlash('Your account is not yet approved. You will receive an e-mail once its approved.');
				}
				else
				{
					//Writing the user information into the session variable and redirecting to the home page.
					$this->Session->setFlash('Login Successful');
					$this->Session->write('User', $user);
					$this->Session->write('Username', $user['Teacher']['name']);
					$this->Session->write('UserType',$user['Teacher']['type']);
					$this->redirect(array(
							'action' => 'index'));
				}
			}
			else
			{
				$this->Session->setFlash('Unable to login. Please check your email address and password that was entered');
			}
		}
	}

	//Method to logout the user.
	public function logout()
	{
		if($this->Session->check('User'))
		{
			$this->Session->destroy();
			$this->Session->setFlash('You have been logged out!');
			$this->redirect(array(
					'controller' => 'pages', 'action' => 'display'));
			//exit();
		}
	}

	//Method that redirects to the home page of the techer model.
	public function home()
	{
		$this->redirect(array('action' => 'index'));
	}

	//Method to register a new teacher into the system.
	public function register()
	{
		//Loading the school model so that the school dropdown can be populated.
		$this->set('schooloptions', ClassRegistry::init('School')->schoolOptions());

		if ($this->request->is('post'))
		{

			//Check to see if the email address already exists in the database.
			if($this->Teacher->checkEmailAddressExists($this->request->data['Teacher']['email_address']))
			{
				$this->Session->setFlash('Email Address already exists. Please try a different one.');
			}
			else
			{
				//If the above validation, along with the model validation is satisfied, create the user profile.

				if ($this->Teacher->createUser($this->request->data))
				{
					$this->Session->setFlash(__('You can enter your data once your profile is approved. Until then check our existing data.'));

					//After registering, the user is redirected to the main page.
					$this->redirect(array(
							'action' => 'index'));
				}
				else
				{
					//Errors that has to be fixed before creating user profile.
					$this->Session->setFlash(__('Unable to create profile. Please try again after correcting the errors shown below.'));
				}
			}
		}
	}

	public function approveUser()
	{
		if($this->authorizedUser())
		{
			$query = $this->Teacher->getUsers();
			$this->set('teachersYetToBeApproved', $query);

			if ($this->request->is('post'))
			{
				$temp['value'] = $this->request->data['Approve'];

				for($i=0; $i<count($query); $i++)
				{
					if(1 == $temp['value'][$query[$i]['Teacher']['id']])
						$this->Teacher->approveUser($query[$i]['Teacher']['id'], 'T');
				}

				$this->set('teachersYetToBeApproved', $this->Teacher->getUsers());
			}
		}
	}

	public function authorizedUser()
	{
		if('A' != $this->Session->read('UserType'))
		{
			$this->Session->setFlash(__('You do not have permissions to access this page !'));
			return false;
		}
		else
			return true;
	}

	public function modifyUser()
	{
		if($this->authorizedUser())
		{
			$userList = $this->Teacher->userList($this->Session->read('User'));
			$this->set('userList', $userList);
		}
	}

	public function editUser($id = null) {
		if (!$id) {
			throw new NotFoundException(__('Invalid Teacher'));
		}

		$teacher = $this->Teacher->getUserDetails($id);

		if (!$teacher) {
			throw new NotFoundException(__('Invalid Teacher'));
		}

		$this->set('schooloptions', ClassRegistry::init('School')->schoolOptions());

		$userTypeOptions = array(
				array(
						'name' => 'Teacher','value' => 'T'),array(
								'name' => 'Admin','value' => 'A'),array(
										'name' => 'Pending','value' => 'P'),);
		$this->set('userTypeOptions', $userTypeOptions);


		if ($this->request->is('post') || $this->request->is('put'))
		{
			if($this->authorizedUser())
			{
				$this->Teacher->id = $id;

				if ($this->Teacher->saveModification($this->request->data))
				{
					$this->Session->setFlash('Teachers detail has been updated.');
					$this->redirect(array('action' => 'modifyUser'));
				}
				else
				{
					$this->Session->setFlash('Unable to update teachers detail.');
				}
			}
		}

		if (!$this->request->data)
		{
			$this->request->data = $teacher;
		}
	}

	public function deleteUser($id,$name)
	{
		if($this->authorizedUser())
		{
			if ($this->request->is('get'))
			{
				throw new MethodNotAllowedException();
			}

			if($this->Teacher->deleteTeacher($id))
			{
				$this->Session->setFlash($name .' has been deleted.');
				$this->redirect(array(
						'action' => 'modifyUser'));
			}
			else
			{
				$this->Session->setFlash('Unable to delete the user.');
			}
		}
	}

	public function userResetPassword($id,$name)
	{

		if($this->authorizedUser())
		{
			if ($this->Teacher->userResetPassword($id))
			{
				$this->Session->setFlash($name .'\'s password has been reset to "CAPLTER".');
				$this->redirect(array(
						'action' => 'modifyUser'));
			}
		}
	}

	public function editProfile()
	{
		if(!$this->Session->check('User'))
		{
			$this->Session->setFlash('Please login to access this page.');
			$this->redirect(array(
					'action' => 'index'));
		}
		$this->set('schooloptions', ClassRegistry::init('School')->schoolOptions());

		$user = $this->Session->read('User');
		$oldEmail = $user['Teacher']['email_address'];

		if ($this->request->is('post') || $this->request->is('put'))
		{
			if($this->request->data['Teacher']['password'] != $this->request->data['Teacher']['confirm_password'])
			{
				$this->Session->setFlash('The passwords you have entered do not match. Please verify the passwords again.');
				return;
			}
			if($oldEmail != $this->request->data['Teacher']['email_address'])
			{
				if($this->Teacher->checkEmailAddressExists($this->request->data['Teacher']['email_address']))
				{
					$this->Session->setFlash('Email Address already exists. Please try a different one.');
				}
				else
				{
					$this->Session->setFlash('Unable to update your profile. Please check the email address that you have entered.');
					return;
				}
			}
			else
			{
				if ($this->Teacher->saveModification($this->request->data))
				{
					$user = $this->Teacher->getUserDetails($user['Teacher']['id']);
					$this->Session->write('User', $user);

					$this->Session->setFlash('Your Profile has been updated.');
					$this->redirect(array(
							'action' => 'index'));
				}
				else
				{
					$this->Session->setFlash('Unable to update your profile.');
					return;
				}
			}
		}

		if (!$this->request->data)
		{
			$this->request->data = $user;
		}
	}

	public function submitData()
	{

		if(!$this->Session->check('User'))
		{
			$this->Session->setFlash('Please login to access the page.');
			$this->redirect(array('action' => 'index'));
		}

		$user = $this->Session->read('User');

		$habitatTypeOptions = array(
				array(
						'name' => 'Arthropods','value' => 'AR'),array(
								'name' => 'Birds','value' => 'BI'),array(
										'name' => 'Bruchids','value' => 'BR'),array(
												'name' => 'Vegetation','value' => 'VE'));
		$this->set('habitatTypeOptions', $habitatTypeOptions);
		$this->set('siteIDOptions', $this->Teacher->getSiteIDs($user));
		$this->set('classIDOptions', $this->Teacher->getClassIDs($user));

		if ($this->request->is('post'))
		{
			if($this->request->data['SubmitData']['protocol'] == 'BR')
			{
				$this->redirect(array(
						'controller' => 'bruchidsamples','action' => 'bruchidData',$this->request->data['SubmitData']['protocol'],$this->request->data['SubmitData']['site'],$this->request->data['SubmitData']['class']));
			}
			else
			{
				$this->redirect(array(
						'controller' => 'habitats','action' => 'habitatCheck',$this->request->data['SubmitData']['protocol'],$this->request->data['SubmitData']['site'],$this->request->data['SubmitData']['class']));
			}
		}
	}

	public function downloadData()
	{
		$habitatTypeOptions = array(
				array(
						'name' => 'Ecology Explorers Arthropod Survey','value' => 'AR'),array(
								'name' => 'Ecology Explorers Bird Survey','value' => 'BI'),array(
										'name' => 'Ecology Explorers Bruchid Survey','value' => 'BR'),array(
												'name' => 'Ecology Explorers Vegetation Survey','value' => 'VE'));
		$this->set('habitatTypeOptions', $habitatTypeOptions);

		$this->set('schooloptions', ClassRegistry::init('School')->schoolOptions());

		if ($this->request->is('post'))
		{

			$dataConditions['protocol'] = $this->request->data['retrieveData']['protocol'];
			$dataConditions['start_date'] = $this->request->data['retrieveData']['start_date']['year'].'-'.$this->request->data['retrieveData']['start_date']['month'].'-'.$this->request->data['retrieveData']['start_date']['day'];
			$dataConditions['end_date'] = $this->request->data['retrieveData']['end_date']['year'].'-'.$this->request->data['retrieveData']['end_date']['month'].'-'.$this->request->data['retrieveData']['end_date']['day'];
			$dataConditions['school_id'] = $this->request->data['retrieveData']['school_id'];
				
			$this->Session->delete('dateRetrieved');
			$this->redirect(array('controller' => 'teachers','action' => 'retrievedData',$dataConditions['protocol'],$dataConditions['start_date'],$dataConditions['end_date'],$dataConditions['school_id']));
		}
	}

	function retrievedData()
	{
		$param = $this->passedArgs;

		$dataConditions['protocol'] = $param[0];
		$dataConditions['start_date'] = $param[1];
		$dataConditions['end_date'] = $param[2];
		$dataConditions['school_id'] = $param[3];
		$this->Session->write('dataConditions',$dataConditions);

		$data = ClassRegistry::init('School')->retrieveData($dataConditions);


		if(empty($data))
		{
			$this->Session->setFlash('No data exists for the given protocol, date range and school combination.');
			$this->redirect(array('controller' => 'teachers','action' => 'downloadData'));
			return;
		}
		$this->Session->write('data',$data);
	}

	function export()
	{
		$this->set('dateRetrieved', $this->Session->read('data'));
		$this->layout = null;
		$this->autoLayout = false;
		Configure::write('debug', '0');
	}


}
