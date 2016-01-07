<?php

namespace Concrete\Package\LdapAuthentication;
use Package;

class Controller extends Package {

    protected $pkgHandle = 'ldap_authentication';
    protected $appversionRequired = '5.7';
    protected $pkgVersion = '0.1';

    public function getPackageDescription() {
       return t('Provides a Ldap Authentication Type for concrete5');
    }
    public function getPackageName() {
       return t('Ldap Authentication');
    }
    
    public function install() {
       $pkg = parent::install();
       \Concrete\Core\Authentication\AuthenticationType::add('ldap', 'Ldap', 0, $pkg);
    }
}
