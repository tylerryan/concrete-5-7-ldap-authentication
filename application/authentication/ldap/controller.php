<?php
namespace Application\Authentication\Ldap;

use Concrete\Core\Authentication\AuthenticationTypeController;
use Config;
use Exception;
use Loader;
use User;
use UserInfo;
use View;

class Controller extends AuthenticationTypeController
{

    //public $apiMethods = array();


    private function __ldap_connect(){
        if (!isset($this->connection)) {
            $con = ldap_connect($this->config('ldaphost'), 389);
            $anon = ldap_bind($con);
            if(!$anon){
                 throw new Exception(t('Failed to connect to LDAP.'));
            }           
            $this->connection = $con;
        }
        
    }

    public function authenticate()
    {

        $post = $this->post();
        $this->__ldap_connect();
        if (empty($post['uName']) || empty($post['uPassword'])) {
            throw new Exception(t('Please provide both username and password.'));
        }
        $uName = $post['uName'];
        $uPassword = $post['uPassword'];

        
        $bindDn = "userid=$uName," . $this->config('ldapdn');
        if(!@ldap_bind($this->connection,$bindDn,$uPassword)){
           throw new \Exception(t('Invalid username or password.'));        
        }
        else {

            $uinf = UserInfo::getByUserName($uName);
           if(!is_object($uinf) || !($uinf instanceof UserInfo) || $uinf->isError()){
            throw new \Exception(t('This account does not exist on this website.'));
          }

           // var_dump($uinf->getUserID());
            \Session::remove('accessEntities');
           $uid = User::getByUserID($uinf->getUserID(),true);

            if(is_object($uid) || ($uid instanceof User) || !$uid->isError()) { 
                 if(!$this->getUserByLdapUser($uName)){
                    $this->mapUserByLdapUser($uName);
                }
                
            }
            else {
                
                switch ($uid->getError()) {
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
            $uid->setAuthTypeCookie('concrete');
        }
       $this->completeAuthentication($uid);
       
    }


    public function getAuthenticationTypeIconHTML()
    {
        return '<i class="fa fa-user"></i>';
    }


    public function getLdapUserInfo()
    {
        $u = new User();
        if (is_object($u) && $u->isLoggedIn()) {
            $db = Loader::db();
            return $db->query('SELECT * FROM authTypeLdapUserData WHERE uID=?', array($u->getUserID()))->fetchRow();
        }
    }

    public function view()
    {
    }

    public function config($key, $value = false)
    {
        $db = Loader::db();
        if ($value === false) {
            return $db->getOne('SELECT value FROM authTypeLdapSettings WHERE setting=?', array($key));
        }
        $db->execute('DELETE FROM authTypeLdapSettings WHERE setting=?', array($key));
        $db->execute('INSERT IGNORE INTO authTypeLdapSettings (setting,value) VALUES (?,?)', array($key, $value));
        return $value;
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
        $this->set('ldaphost', $this->config('ldaphost'));
        $this->set('ldapdn', $this->config('ldapdn'));
    }

    public function saveAuthenticationType($args)
    {
        $this->config('ldapdn', $args['ldapdn']);
        $this->config('ldaphost', $args['ldaphost']);
    }

    public function getUserByLdapUser($ldu)
    {
        $db = Loader::db();
        $uid = $db->getOne('SELECT uID FROM authTypeLdapUserMap WHERE ldUserID=?', array($ldu));
        // if (!$uid) {
        //     throw new \Exception(t('This Ldap account is not tied to a user.'));
        // }
        return $uid;
    }

    public function mapUserByLdapUser($ldu)
    {
        $u = new User();
        $db = Loader::db();
       
        $db->execute('DELETE FROM authTypeLdapUserMap WHERE ldUserID=? OR uID=?', array($ldu, $u->getUserID()));
        $db->execute('INSERT INTO authTypeLdapUserMap (ldUserID,uID) VALUES (?,?)', array($ldu, $u->getUserID()));
        return true;
    }

    private function genString($a = 20)
    {
        $o = '';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+{}|":<>?\'\\';
        $l = strlen($chars);
        while ($a--) {
            $o .= substr($chars, rand(0, $l), 1);
        }
        return md5($o);
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
}
