<?php defined('C5_EXECUTE') or die('Access denied.'); ?>
<div class='form-group'>
    <?php echo $form->label('ldaphost', t('LDAP Host'))?>
    <?php echo $form->text('ldaphost', $ldaphost)?>
</div>
<div class='form-group'>
    <?php echo $form->label('ldapdn', t('LDAP Base DN'))?>
    <?php echo $form->text('ldapdn', $ldapdn)?>
</div>
