<?php
	use \DrewM\MailChimp\MailChimp;
	
	class Accounts extends CI_Controller
	{
		public $default_mailchimp_list = '6b72c053d5';
		
		public function __construct()
		{
			parent::__construct();
			
			$this->load->model('Accounts_Model');
			$this->load->model('Teams_Model');
			$this->load->model('Projects_Model');
			$this->load->model('Companies_Model');
		}
		
		public function index()
		{
			if($this->Permissions_Model->is_admin())
			{
				$data = array
				(
					'webpage_title'	=>	'Accounts',
				);
				
				if($this->Accounts_Model->get_accounts()) {
					$data['accounts'] = $this->Accounts_Model->results;
				}
			
				$this->load->template('accounts/view_accounts', $data);
			}
			else
			{
				$data['webpage_title'] = 'Accounts';
				$data['accounts'] = array();
				
				/* Get accounts array based on company_id */
				if($this->Accounts_Model->get_accounts($this->session->userdata('company')['company_id']))
				{
					$data['accounts'] = $this->Accounts_Model->results;
				}
				
				$this->load->template('accounts/companies_accounts', $data);
			}
		}
		
		public function assign_account($company_id, $account_id)
		{
			$data = array(); /* init empty array */
			$data['teams'] = array();
			
			if($this->Teams_Model->get_teams($company_id))
			{
				$data['teams'] = $this->Teams_Model->results;
				$data['company_id'] = $company_id;
				$data['account_id'] = $account_id; 
			}
			
			$this->load->view('accounts/assign_account', $data);
		}
		
		public function assign_account_process()
		{
			if(intval($this->input->post('team_id')) == 0)
			{
				$response['status'] = 400;
				$response['errors'] = '<p>Please select a team</p>';
			}
			else
			{
				$data = array
				(
					'team_id'		=>	$this->input->post('team_id'),
					'account_id'	=>	$this->input->post('account_id')
				);
				
				if($this->Accounts_Model->assign_account($data) === TRUE)
				{
					$response['status'] = 	200;
					$response['url']	=	'refresh';
				}
				else
				{
					$response['status'] = 400;
					$response['errors'] = '<p>General error. Please contact support.</p>';
				}
			}
			
			header('Content-Type: application/json');
			echo json_encode($response);
		}
		
		public function profile()
		{
			$this->load->template('accounts/profile');
		}
		
		
		/* to be deprecated */
		
		public function view_account($account_id)
		{
			$this->load->model('Permissions_Model'); 
			$account = $this->Accounts_Model->get_account($account_id);
			$permissions = $this->Permissions_Model->get_roles_categories();
			
			$data = array
			(
				'webpage_title' => 'Name',
				'account'	=>	$account,
				'permissions'	=>	$permissions
			);
			
			$this->load->template('accounts/view_account', $data);
		}
		
		
		
		public function login_page()
		{
			$this->form_validation->set_rules('login_email', 'Email', 'required|valid_email');
			$this->form_validation->set_rules('login_password', 'Password', 'required|callback_valid_account');
			
			if($this->form_validation->run() === FALSE)
			{
				$this->load->view('accounts/login');
			}
			else
			{
				redirect('/');
			}
		}
		
		public function valid_account()
		{
			$data = array
			(
				'account_email' => $this->input->post('login_email'),
				'account_password' => $this->input->post('login_password')
			);
			$result = $this->Accounts_Model->login_account($data);
			
			if($result==false)
			{
				$this->form_validation->set_message('valid_account', 'Invalid username or password');
				return false;
			}
			else
			{
				$this->Companies_Model->get_companies($result->account_id);
				
				$session_array = array
				(
					'account_loggedin'		=>	true,
					'account_id' 			=>	$result->account_id,
					'account_fname' 		=>	$result->account_fname,
					'account_lname' 		=>	$result->account_lname,
					'account_email' 		=>	$result->account_email,
					'account_phone' 		=>	$result->account_phone,
					'account_group_id'		=>	$result->account_group_id,
					'account_isadmin'		=>	$result->account_isadmin,
					'companies'				=>	$this->Companies_Model->results
				);
				
				if($this->Accounts_Model->get_default_company($result->account_id))
				{
					$default_company = $this->Accounts_Model->results;
					$session_array['company'] = $default_company;
					
					if($this->Projects_Model->get_default_project($default_company['company_id']))
					{
						$session_array['project'] = $this->Projects_Model->results;
					}
				}
				
				$this->session->set_userdata($session_array);
				return true;
			}
		}
		
		public function create_account()
		{
			$this->form_validation->set_rules('account_name', 'Full name', 'required');
			// $this->form_validation->set_rules('account_phone', 'Phone', 'required|is_unique[accounts.account_phone]|regex_match[/^[0-9]{11}$/]');
			$this->form_validation->set_rules('account_email', 'Email', 'required|valid_email|is_unique[accounts.account_email]');
			$this->form_validation->set_rules('account_password', 'Password', 'required');
			$this->form_validation->set_rules('account_password_confirm', 'Confirm Password', 'required|matches[account_password]');
			
			if ($this->form_validation->run() === FALSE)
			{
				$response['status'] = 	400;
				$response['errors']	=	validation_errors();
			}
			else
			{
				$account_name = explode(' ', $this->input->post('account_name'));
				
				if(count($account_name)==2)
				{
					$account_fname = $account_name[0];
					$account_lname	= $account_name[1];
				}
				else // multiple names
				{
					$account_fname = array();
					
					foreach($account_name as $name)
					{
						// remove last name
						if($name!=$account_name[count($account_name)-1])
						{
							array_push($account_fname, $name);
						}
					}
					
					$account_fname = implode(' ', $account_fname);
					$account_lname = $account_name[count($account_name)-1]; // Last chunk from the array will be the last name
				}
				
				$data = array
				(
					'company_id'			=>	$this->input->post('company_id'),
					'account_group_id' 		=> 	get_setting('default_user_group_id'),
					'account_fname' 		=> 	$account_fname,
					'account_lname' 		=> 	$account_lname,
					'account_email' 		=> 	$this->input->post('account_email'),
					'account_email_code' 	=> 	$this->Accounts_Model->generate_verification_code(),
					'account_phone' 		=> 	$this->input->post('account_phone'),
					'account_phone_code' 	=> 	$this->Accounts_Model->generate_verification_code(),
					'account_password' 		=> 	password_encrypt($this->input->post('account_password')),
					'account_avatar'		=>	$this->Accounts_Model->get_random_avatar(),
					'account_created' 		=> 	get_current_datetime()
				);
				
				if($this->Accounts_Model->register_account($data)==true)
				{
					if(intval($this->input->post('account_credentials_email'))===1)
					{						
						/* SEND CREDENTIALS THROUGH EMAIL */
						$data['account_password'] = $this->input->post('account_password');
						$this->load->model('Emails_Model');
						$this->Emails_Model->send_notification($data);
					}
					
					if(intval($this->input->post('account_credentials_phone'))===1)
					{
						/* SEND CREDENTIALS THROUGH SMS */
						$this->load->model('SMS_Model');
						$this->SMS_Model->send_sms($this->input->post('account_phone'), 'Your Package7 account is ' . $this->input->post('account_email') . ' with ' . $this->input->post('account_password') . ' as a password. Enjoy!');
					}
					$response['status'] = 	200;
					$response['url']	=	'refresh';
				}
				else
				{
					$response['status'] = 	400;
					$response['errors']	=	$this->Accounts_Model->message;
				}
			}
			
			header('Content-Type: application/json');
			echo json_encode($response);
		}
		
		public function register_page()
		{
			
			
			$this->form_validation->set_rules('account_name', 'Full name', 'required');
			$this->form_validation->set_rules('account_phone', 'Phone', 'required|is_unique[accounts.account_phone]|regex_match[/^[0-9]{11}$/]');
			$this->form_validation->set_rules('account_email', 'Email', 'required|valid_email|is_unique[accounts.account_email]');
			$this->form_validation->set_rules('account_password', 'Password', 'required');
			
			if ($this->form_validation->run() === FALSE)
			{	
				$this->load->view('accounts/register');
			}
			else
			{
				$account_name = explode(' ', $this->input->post('account_name'));
				
				if(count($account_name)==2)
				{
					$account_fname = $account_name[0];
					$account_lname	= $account_name[1];
				}
				else // multiple names
				{
					$account_fname = array();
					
					foreach($account_name as $name)
					{
						// remove last name
						if($name!=$account_name[count($account_name)-1])
						{
							array_push($account_fname, $name);
						}
					}
					
					$account_fname = implode(' ', $account_fname);
					$account_lname = $account_name[count($account_name)-1]; // Last chunk from the array will be the last name
				}
				
				$account_phone = $this->input->post('account_phone');
				$account_code = $this->Accounts_Model->generate_verification_code();
				
				$data = array
				(
					'account_group_id' 		=> 	get_setting('default_user_group_id'),
					'account_fname' 		=> 	$account_fname,
					'account_lname' 		=> 	$account_lname,
					'account_email' 		=> 	$this->input->post('account_email'),
					'account_email_code' 	=> 	$this->Accounts_Model->generate_verification_code(),
					'account_phone' 		=> 	$account_phone,
					'account_phone_code' 	=> 	$this->Accounts_Model->generate_verification_code(),
					'account_password' 		=> 	password_encrypt($this->input->post('account_password')),
					'account_avatar'		=>	$this->Accounts_Model->get_random_avatar(),
					'account_created' 		=> 	date('Y-m-d H:i:s')
				);
				
				if($this->Accounts_Model->register_account($data)==true)
				{
					$this->load->model('Emails_Model');
					$this->Emails_Model->send_activation_code($data);
					
					$this->load->model('SMS_Model');
					$this->SMS_Model->send_sms($account_phone, $data['account_phone_code'] . ' is your Package7 account activation code');
					
					redirect('/activate');
				}
			}
		}
		
		public function activate_page()
		{
			$this->form_validation->set_rules('account_code', 'Activation code', 'required');
			
			if ($this->form_validation->run() === FALSE)
			{	
				$data['account_code'] = $this->uri->segment(2);
				$this->load->template('accounts/activate', $data);
			}
			else
			{
				if($this->Accounts_Model->activate_account($this->input->post('account_code')))
				{
					redirect('/login?activated=true');
				}
				else
				{
					redirect('/login?activated=false');
				}
			}
		}
		
		public function logout()
		{
			$session_array = array();
			$this->session->unset_userdata('loggedin', $session_array);
			session_destroy();
			redirect('/');
		}
		
		public function view()
		{
			$account_id = $this->session->userdata('account_id');
			
			$account = $this->Accounts_Model->get_account($account_id);
			
			$data = array
			(
				'webpage_title' => 'Account',
				'account' => $account
			);
			
			$this->load->template('accounts/view', $data);
		}
		
		public function activate()
		{
		}
		
		public function login()
		{
		}
		
		public function forgot_password()
		{
			$this->load->template('accounts/forgot_password');
		}
		
		public function edit_account()
		{
			echo 'edit account';
		}
	}
	
?>