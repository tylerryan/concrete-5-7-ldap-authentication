<?php
namespace Concrete\Package\LdapAuthentication\Authentication\Ldap;

use Concrete\Core\Authentication\AuthenticationTypeController;
use Config;
use Exception;
use Loader;
use User;
use UserInfo;
use View;

class Controller extends AuthenticationTypeController
{
    
    public $testMode = true;
    
    public function getHandle() {
        return 'ldap';
    }

    //public $apiMethods = array();


    private function __ldap_connect(){
        if (!isset($this->connection)) {
            $con = ldap_connect(Config::get('auth.ldap.ldaphost'), 389);
            $anon = ldap_bind($con);
            if(!$anon){
                 throw new Exception(t('Failed to connect to LDAP.'));
            }           
            $this->connection = $con;
        }
        
    }

    public function authenticate()
    {
        //@todo sanitize post input
        $post = $this->post();
        $this->__ldap_connect();
        if (empty($post['uName']) || empty($post['uPassword'])) {
            throw new Exception(t('Please provide both username and password.'));
        }
        $domain = trim(Config::get('auth.ldap.ldapdomain'));
        $uName = $post['uName'];
        if(strlen($domain)) {
            $ldapLoginuName = Config::get('auth.ldap.ldapdomain').'\\'.$uName;    
        }
        
        $uPassword = $post['uPassword'];

        if(!@ldap_bind($this->connection,$ldapLoginuName,$uPassword)){
           throw new \Exception(t('Invalid username or password.'));        
        } else {
            \Session::remove('accessEntities');
            
           $uID = $this->getUserByLdapUser($uName);
           if($uID) { // ldap user has been bound to a c5 user
            $ui = UserInfo::getByID($uID);    
           }
           
           
           if(!is_object($ui) || !($ui instanceof UserInfo) || $ui->isError()) { // user needs to be created
             $user = $this->createUser($uName, $uName."@".$domain.'.com'); // @TODO email is a total hack right now - fix it.
           } else {
              $user = \User::loginByUserID($ui->getUserID());
           }
           
           if(is_object($user) && $user->isError()) { 
                switch ($user->getError()) {
                    case USER_SESSION_EXPIRED:
                        throw new \Exception(t('Your session has expired. Please sign in again.'));
                        break;
                    case USER_NON_VALIDATED:
                        throw new \Exception(t('This account has not yet been validated. Please check the email associated with this account and follow the link it contains.'));
                        break;
                    case USER_INVALID:
                        if (Config::get('concrete.user.registration.email_registration')) {
                            throw new \Exception(t('Invalid email address or password.'));
                        } else {
                            throw new \Exception(t('Invalid username or password.'));
                        }
                        break;
                    case USER_INACTIVE:
                        throw new \Exception(t('This user is inactive. Please contact the helpdesk regarding this account.'));
                        break;
                }
            }
        }

        if ($post['uMaintainLogin']) {
            $user->setAuthTypeCookie('ldap');
        }
       $this->completeAuthentication($user);
       
    }


    public function getAuthenticationTypeIconHTML()
    {
        return '<i class="fa fa-user"></i>';
    }


    public function view()
    {
    }

    public function getLdapUserByUser($uid)
    {
        $db = Loader::db();
        $lduid = $db->getOne('SELECT ldUserID FROM authTypeLdapUserMap WHERE uID=?', array($uid));
        if (!$lduid) {
            throw new \Exception(t('This user is not tied to a Ldap account.'));
        }
        return $lduid;
    }

    public function edit()
    {
        $this->set('form', Loader::helper('form'));
        $this->set('ldaphost', \Config::get('auth.ldap.ldaphost'));
        $this->set('ldapdn', \Config::get('auth.ldap.ldapdn'));
        $this->set('ldapdomain', \Config::get('auth.ldap.ldapdomain'));
    }

    public function saveAuthenticationType($args)
    {
        \Config::save('auth.ldap.ldapdn', $args['ldapdn']);
        \Config::save('auth.ldap.ldaphost', $args['ldaphost']);
        \Config::save('auth.ldap.ldapdomain', $args['ldapdomain']);
    }

    public function getUserByLdapUser($ldu)
    {
        $db = Loader::db();
        $uid = $db->getOne('SELECT uID FROM authTypeLdapUserMap WHERE ldUserID=?', array($ldu));
        User::getByUserID($uid);
        
        if (!$uid) {
            return false;
        } else { 
            return $uid;
        }
    }

    public function mapUserByLdapUser($ldu, $uID)
    {
        $db = Loader::db();
       
        $db->execute('DELETE FROM authTypeLdapUserMap WHERE ldUserID=? OR uID=?', array($ldu, $uID));
        $db->execute('INSERT INTO authTypeLdapUserMap (ldUserID,uID) VALUES (?,?)', array($ldu, $uID));
        return true;
    }

    public function deauthenticate(User $u)
    {
    }

    public function verifyHash(User $u, $hash)
    {
        // This currently does nothing.
        return true;
    }

    public function buildHash(User $u, $test = 1)
    {
        // This doesn't do anything.
        return 1;
    }

    public function isAuthenticated(User $u)
    {
        return ($u->isLoggedIn());
    }
    
    public function registrationGroupID() 
    {
        return 0;
    }
    
    protected function createUser($username, $email, $first_name='', $last_name = '')
    {

        $data = array();
        $data['uName'] = $username;
        $data['uPassword'] = \Illuminate\Support\Str::random(256);
        $data['uEmail'] = $email;
        $data['uIsValidated'] = 1;

        $user_info = \UserInfo::add($data);

        if (!$user_info) {
            throw new Exception('Unable to create new account.');
        }

        if ($group_id = intval($this->registrationGroupID(), 10)) {
            $group = \Group::getByID($group_id);
            if ($group && is_object($group) && !$group->isError()) {
                $user = \User::getByUserID($user_info->getUserID());
                $user->enterGroup($group);
            }
        }

        $key = \UserAttributeKey::getByHandle('first_name');
        if ($key) {
            $user_info->setAttribute($key, $first_name);
        }

        $key = \UserAttributeKey::getByHandle('last_name');
        if ($key) {
            $user_info->setAttribute($key, $last_name);
        }

        $user = \User::loginByUserID($user_info->getUserID());

        $this->mapUserByLdapUser($username, $user_info->getUserID());

        return $user;
    }
    
    /*
     * Query the ldap server for the user's info (name, email etc..)
     * @TODO make this work
     */
    public function getLdapUserInfo($uName) {
        return array();
    }
    
}
