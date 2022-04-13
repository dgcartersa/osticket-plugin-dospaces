<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class S3StoragePluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-ldap');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'bucket' => new TextboxField(array(
                'label' => $__('Space Name'),
                'configuration' => array('size'=>40),
            )),
            'folder' => new TextboxField(array(
                'label' => $__('Space Folder Path'),
                'configuration' => array('size'=>40),
            )),
            'aws-region' => new ChoiceField(array(
                'label' => $__('DO Spaces Region'),
                'choices' => array(
                    'ams3' => 'Amsterdam 3',
                    'nyc3' => 'New Youk 3',
                ),
                'default' => 'ams3',
            )),
            'acl' => new ChoiceField(array(
                'label' => $__('Default ACL for Attachments'),
                'choices' => array(
                    '' => $__('Use Space Default'),
                    'private' => $__('Private'),
                    'public-read' => $__('Public Read'),
                    'public-read-write' => $__('Public Read and Write'),
                    'authenticated-read' => $__('Read for AWS authenticated Users'),
                    'bucket-owner-read' => $__('Read for Bucket Owners'),
                    'bucket-owner-full-control' => $__('Full Control for Bucket Owners'),
                ),
                'default' => '',
            )),

            'access-info' => new SectionBreakField(array(
                'label' => $__('Access Information'),
            )),
            'aws-key-id' => new TextboxField(array(
                'required' => true,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => $__('Space Access Key ID'),
            )),
            'secret-access-key' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'required' => false,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => $__('Space Secret Access Key'),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();
        $credentials['credentials'] = array(
            'key' => $config['aws-key-id'],
            'secret' => $config['secret-access-key']
                ?: Crypto::decrypt($this->get('secret-access-key'), SECRET_SALT,
                        $this->getNamespace()),
        );
        if ($config['aws-region'])
            $credentials['region'] = $config['aws-region'];

        if (!$credentials['credentials']['secret'])
            $this->getForm()->getField('secret-access-key')->addError(
                $__('Secret access key is required'));

        $credentials['version'] = '2006-03-01';
        $credentials['signature_version'] = 'v4';

        $s3 = new Aws\S3\S3Client($credentials);

        try {
            $s3->headBucket(array('Bucket'=>$config['bucket']));
        }
        catch (Aws\S3\Exception\AccessDeniedException $e) {
            $errors['err'] = sprintf(
                /* The %s token will become an upstream error message */
                $__('User does not have access to this bucket: %s'), (string)$e);
        }
        catch (Aws\S3\Exception\NoSuchBucketException $e) {
            $this->getForm()->getField('bucket')->addError(
                $__('Bucket does not exist'));
        }

        if (!$errors && $config['secret-access-key'])
            $config['secret-access-key'] = Crypto::encrypt($config['secret-access-key'],
                SECRET_SALT, $this->getNamespace());
        else
            $config['secret-access-key'] = $this->get('secret-access-key');

        return true;
    }
}
