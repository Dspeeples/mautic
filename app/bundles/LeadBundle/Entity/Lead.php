<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\UserBundle\Entity\User;
use Mautic\NotificationBundle\Entity\PushID;
use Mautic\StageBundle\Entity\Stage;

/**
 * Class Lead
 *
 * @package Mautic\LeadBundle\Entity
 */
class Lead extends FormEntity
{
    /**
     * Used to determine social identity
     *
     * @var array
     */
    private $availableSocialFields = [];

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Mautic\UserBundle\Entity\User
     */
    private $owner;

    /**
     * @var int
     */
    private $points = 0;

    /**
     * @var ArrayCollection
     */
    private $pointsChangeLog;

    /**
     * @var ArrayCollection
     */
    private $doNotContact;

    /**
     * @var ArrayCollection
     */
    private $ipAddresses;

    /**
     * @var ArrayCollection
     */
    private $pushIds;

    /**
     * @var \DateTime
     */
    private $lastActive;

    /**
     * @var array
     */
    private $internal = [];

    /**
     * @var array
     */
    private $socialCache = [];

    /**
     * Just a place to store updated field values so we don't have to loop through them again comparing
     *
     * @var array
     */
    private $updatedFields = [];

    /**
     * Used to populate trigger color
     *
     * @var string
     */
    private $color;

    /**
     * Sets if the IP was just created by LeadModel::getCurrentLead()
     *
     * @var bool
     */
    private $newlyCreated = false;

    /**
     * @var \DateTime
     */
    private $dateIdentified;

    /**
     * @var ArrayCollection
     */
    private $notes;

    /**
     * Used by Mautic to populate the fields pulled from the DB
     *
     * @var array
     */
    protected $fields = [];

    /**
     * @var string
     */
    private $preferredProfileImage;

    /**
     * Changed to true if the lead was anonymous before updating fields
     *
     * @var null
     */
    private $wasAnonymous = null;

    /**
     * @var bool
     */
    public $imported = false;

    /**
     * @var ArrayCollection
     */
    private $tags;

    /**
     * @var \Mautic\StageBundle\Entity\Stage
     */
    private $stage;
    /**
     * @var ArrayCollection
     */
    private $stageChangeLog;

    /**
     * @var ArrayCollection
     */
    private $utmtags;

    /**
     * @var \Mautic\LeadBundle\Entity\FrequencyRule
     */
    private $frequencyRules;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ipAddresses     = new ArrayCollection();
        $this->pushIds         = new ArrayCollection();
        $this->doNotContact    = new ArrayCollection();
        $this->pointsChangeLog = new ArrayCollection();
        $this->tags            = new ArrayCollection();
        $this->stageChangeLog  = new ArrayCollection();
        $this->frequencyRules  = new ArrayCollection();
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __get($name)
    {
        return $this->getFieldValue(strtolower($name));
    }

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('leads')
            ->setCustomRepositoryClass('Mautic\LeadBundle\Entity\LeadRepository')
            ->addLifecycleEvent('checkDateIdentified', 'preUpdate')
            ->addLifecycleEvent('checkDateIdentified', 'prePersist')
            ->addLifecycleEvent('checkAttributionDate', 'preUpdate')
            ->addLifecycleEvent('checkAttributionDate', 'prePersist')
            ->addIndex(['date_added'], 'lead_date_added');

        $builder->createField('id', 'integer')
            ->isPrimaryKey()
            ->generatedValue()
            ->build();

        $builder->createManyToOne('owner', 'Mautic\UserBundle\Entity\User')
            ->addJoinColumn('owner_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->createField('points', 'integer')
            ->build();

        $builder->createOneToMany('pointsChangeLog', 'PointsChangeLog')
            ->orphanRemoval()
            ->setOrderBy(['dateAdded' => 'DESC'])
            ->mappedBy('lead')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        $builder->createOneToMany('doNotContact', 'Mautic\LeadBundle\Entity\DoNotContact')
            ->orphanRemoval()
            ->mappedBy('lead')
            ->cascadePersist()
            ->fetchExtraLazy()
            ->build();

        $builder->createManyToMany('ipAddresses', 'Mautic\CoreBundle\Entity\IpAddress')
            ->setJoinTable('lead_ips_xref')
            ->addInverseJoinColumn('ip_id', 'id', false)
            ->addJoinColumn('lead_id', 'id', false, false, 'CASCADE')
            ->setIndexBy('ipAddress')
            ->cascadeMerge()
            ->cascadePersist()
            ->cascadeDetach()
            ->build();

        $builder->createOneToMany('pushIds', 'Mautic\NotificationBundle\Entity\PushID')
            ->orphanRemoval()
            ->mappedBy('lead')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        $builder->createField('lastActive', 'datetime')
            ->columnName('last_active')
            ->nullable()
            ->build();

        $builder->createField('internal', 'array')
            ->nullable()
            ->build();

        $builder->createField('socialCache', 'array')
            ->columnName('social_cache')
            ->nullable()
            ->build();

        $builder->createField('dateIdentified', 'datetime')
            ->columnName('date_identified')
            ->nullable()
            ->build();

        $builder->createOneToMany('notes', 'LeadNote')
            ->orphanRemoval()
            ->setOrderBy(['dateAdded' => 'DESC'])
            ->mappedBy('lead')
            ->fetchExtraLazy()
            ->build();

        $builder->createField('preferredProfileImage', 'string')
            ->columnName('preferred_profile_image')
            ->nullable()
            ->build();

        $builder->createManyToMany('tags', 'Mautic\LeadBundle\Entity\Tag')
            ->setJoinTable('lead_tags_xref')
            ->addInverseJoinColumn('tag_id', 'id', false)
            ->addJoinColumn('lead_id', 'id', false, false, 'CASCADE')
            ->setOrderBy(['tag' => 'ASC'])
            ->setIndexBy('tag')
            ->fetchLazy()
            ->cascadeMerge()
            ->cascadePersist()
            ->cascadeDetach()
            ->build();

        $builder->createManyToOne('stage', 'Mautic\StageBundle\Entity\Stage')
            ->cascadePersist()
            ->cascadeMerge()
            ->addJoinColumn('stage_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->createOneToMany('stageChangeLog', 'StagesChangeLog')
            ->orphanRemoval()
            ->setOrderBy(['dateAdded' => 'DESC'])
            ->mappedBy('lead')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        $builder->createOneToMany('utmtags', 'Mautic\LeadBundle\Entity\UtmTag')
            ->orphanRemoval()
            ->mappedBy('lead')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        $builder->createOneToMany('frequencyRules', 'Mautic\LeadBundle\Entity\FrequencyRule')
            ->orphanRemoval()
            ->setIndexBy('channel')
            ->setOrderBy(['dateAdded' => 'DESC'])
            ->mappedBy('lead')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();
    }

    /**
     * Prepares the metadata for API usage
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('lead')
            ->setRoot('lead')
            ->addListProperties(
                [
                    'id',
                    'points',
                    'color',
                    'fields',
                ]
            )
            ->addProperties(
                [
                    'lastActive',
                    'owner',
                    'ipAddresses',
                    'tags',
                    'utmtags',
                    'stage',
                    'dateIdentified',
                    'preferredProfileImage'
                ]
            )
            ->build();
    }

    /**
     * @param string $prop
     * @param mixed  $val
     */
    protected function isChanged($prop, $val)
    {
        $getter  = "get".ucfirst($prop);
        $current = $this->$getter();
        if ($prop == 'owner') {
            if ($current && !$val) {
                $this->changes['owner'] = [$current->getName().' ('.$current->getId().')', $val];
            } elseif (!$current && $val) {
                $this->changes['owner'] = [$current, $val->getName().' ('.$val->getId().')'];
            } elseif ($current && $val && $current->getId() != $val->getId()) {
                $this->changes['owner'] = [
                    $current->getName().'('.$current->getId().')',
                    $val->getName().'('.$val->getId().')'
                ];
            }
        } elseif ($prop == 'ipAddresses') {
            $this->changes['ipAddresses'] = ['', $val->getIpAddress()];
        } elseif ($prop == 'tags') {
            if ($val instanceof Tag) {
                $this->changes['tags']['added'][] = $val->getTag();
            } else {
                $this->changes['tags']['removed'][] = $val;
            }
        } elseif ($prop == 'utmtags') {

            if ($val instanceof UtmTag) {
                if ($val->getUtmContent()) {
                    $this->changes['utmtags'] = ['utm_content', $val->getUtmContent()];
                }
                if ($val->getUtmMedium()) {
                    $this->changes['utmtags'] = ['utm_medium', $val->getUtmMedium()];
                }
                if ($val->getUtmCampaign()) {
                    $this->changes['utmtags'] = ['utm_campaign', $val->getUtmCampaign()];
                }
                if ($val->getUtmTerm()) {
                    $this->changes['utmtags'] = ['utm_term', $val->getUtmTerm()];
                }
                if ($val->getUtmSource()) {
                    $this->changes['utmtags'] = ['utm_source', $val->getUtmSource()];
                }

            }
        } elseif ($prop == 'frequencyRules') {

            if ($val instanceof FrequencyRule) {
                if ($val->getFrequencyTime()) {
                    $this->changes['frequencyRules'] = ['frequency_time', $val->getFrequencyTime()];
                }
                if ($val->getFrequencyNumber()) {
                    $this->changes['frequencyRules'] = ['frequency_number', $val->getFrequencyNumber()];
                }
            } else {
                $this->changes['frequencyRules']['removed'][] = $val;
            }
        } elseif ($this->$getter() != $val) {
            $this->changes[$prop] = [$this->$getter(), $val];
        }
    }

    /**
     * @return array
     */
    public function convertToArray()
    {
        return get_object_vars($this);
    }

    /**
     * Set id
     *
     * @param integer $id
     *
     * @return Lead
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set owner
     *
     * @param User $owner
     *
     * @return Lead
     */
    public function setOwner(User $owner = null)
    {
        $this->isChanged('owner', $owner);
        $this->owner = $owner;

        return $this;
    }

    /**
     * Get owner
     *
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Add ipAddress
     *
     * @param IpAddress $ipAddress
     *
     * @return Lead
     */
    public function addIpAddress(IpAddress $ipAddress)
    {
        if (!$ipAddress->isTrackable()) {
            return $this;
        }

        $ip = $ipAddress->getIpAddress();
        if (!isset($this->ipAddresses[$ip])) {
            $this->isChanged('ipAddresses', $ipAddress);
            $this->ipAddresses[$ip] = $ipAddress;
        }

        return $this;
    }

    /**
     * Remove ipAddress
     *
     * @param IpAddress $ipAddress
     */
    public function removeIpAddress(IpAddress $ipAddress)
    {
        $this->ipAddresses->removeElement($ipAddress);
    }

    /**
     * Get ipAddresses
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getIpAddresses()
    {
        return $this->ipAddresses;
    }

    /**
     * Get full name
     *
     * @param bool $lastFirst
     *
     * @return string
     */
    public function getName($lastFirst = false)
    {
        if (isset($this->updatedFields['firstname'])) {
            $firstName = $this->updatedFields['firstname'];
        } else {
            $firstName = (isset($this->fields['core']['firstname']['value'])) ? $this->fields['core']['firstname']['value'] : '';
        }

        if (isset($this->updatedFields['lastname'])) {
            $lastName = $this->updatedFields['lastname'];
        } else {
            $lastName = (isset($this->fields['core']['lastname']['value'])) ? $this->fields['core']['lastname']['value'] : '';
        }

        $fullName = "";
        if ($lastFirst && !empty($firstName) && !empty($lastName)) {
            $fullName = $lastName.", ".$firstName;
        } elseif (!empty($firstName) && !empty($lastName)) {
            $fullName = $firstName." ".$lastName;
        } elseif (!empty($firstName)) {
            $fullName = $firstName;
        } elseif (!empty($lastName)) {
            $fullName = $lastName;
        }

        return $fullName;
    }

    /**
     * Get company
     *
     * @return string
     */
    public function getCompany()
    {
        if (isset($this->updatedFields['company'])) {

            return $this->updatedFields['company'];
        }

        if (!empty($this->fields['core']['company']['value'])) {

            return $this->fields['core']['company']['value'];
        }

        return '';
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        if (isset($this->updatedFields['email'])) {

            return $this->updatedFields['email'];
        }

        if (!empty($this->fields['core']['email']['value'])) {

            return $this->fields['core']['email']['value'];
        }

        return '';
    }

    /**
     * Get preferred locale
     *
     * @return string
     */
    public function getPreferredLocale()
    {
        if (isset($this->updatedFields['preferred_locale'])) {

            return $this->updatedFields['preferred_locale'];
        }

        if (!empty($this->fields['core']['preferred_locale']['value'])) {

            return $this->fields['core']['preferred_locale']['value'];
        }

        return '';
    }

    /**
     * Get lead field value
     *
     * @param      $field
     * @param null $group
     *
     * @return bool
     */
    public function getFieldValue($field, $group = null)
    {
        if (isset($this->updatedFields[$field])) {

            return $this->updatedFields[$field];
        }

        if (!empty($group) && isset($this->fields[$group][$field])) {

            return $this->fields[$group][$field]['value'];
        }

        foreach ($this->fields as $group => $groupFields) {
            foreach ($groupFields as $name => $details) {
                if ($name == $field) {

                    return $details['value'];
                }
            }
        }

        return false;
    }

    /**
     * Get the primary identifier for the lead
     *
     * @param bool $lastFirst
     *
     * @return string
     */
    public function getPrimaryIdentifier($lastFirst = false)
    {
        if ($name = $this->getName($lastFirst)) {
            return $name;
        } elseif (!empty($this->fields['core']['company']['value'])) {
            return $this->fields['core']['company']['value'];
        } elseif (!empty($this->fields['core']['email']['value'])) {
            return $this->fields['core']['email']['value'];
        } elseif (count($ips = $this->getIpAddresses())) {
            return $ips->first()->getIpAddress();
        } elseif ($socialIdentity = $this->getFirstSocialIdentity()) {
            return $socialIdentity;
        } else {
            return 'mautic.lead.lead.anonymous';
        }
    }

    /**
     * Get the secondary identifier for the lead; mainly company
     *
     * @return string
     */
    public function getSecondaryIdentifier()
    {
        if (!empty($this->fields['core']['company']['value'])) {
            return $this->fields['core']['company']['value'];
        }

        return '';
    }

    /**
     * Get the location for the lead
     *
     * @return string
     */
    public function getLocation()
    {
        $location = '';

        if (!empty($this->fields['core']['city']['value'])) {
            $location .= $this->fields['core']['city']['value'].', ';
        }

        if (!empty($this->fields['core']['state']['value'])) {
            $location .= $this->fields['core']['state']['value'].', ';
        }

        if (!empty($this->fields['core']['country']['value'])) {
            $location .= $this->fields['core']['country']['value'].', ';
        }

        return rtrim($location, ', ');
    }

    /**
     * Adds/substracts from current points
     *
     * @param $points
     */
    public function addToPoints($points)
    {
        $newPoints = $this->points + $points;
        $this->setPoints($newPoints);
    }

    /**
     * Set points
     *
     * @param integer $points
     *
     * @return Lead
     */
    public function setPoints($points)
    {
        $this->isChanged('points', $points);
        $this->points = $points;

        return $this;
    }

    /**
     * Get points
     *
     * @return integer
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * Creates a points change entry
     *
     * @param           $type
     * @param           $name
     * @param           $action
     * @param           $pointsDelta
     * @param IpAddress $ip
     */
    public function addPointsChangeLogEntry($type, $name, $action, $pointsDelta, IpAddress $ip)
    {
        if ($pointsDelta === 0) {
            // No need to record a null delta
            return;
        }

        // Create a new points change event
        $event = new PointsChangeLog();
        $event->setType($type);
        $event->setEventName($name);
        $event->setActionName($action);
        $event->setDateAdded(new \DateTime());
        $event->setDelta($pointsDelta);
        $event->setIpAddress($ip);
        $event->setLead($this);
        $this->addPointsChangeLog($event);
    }

    /**
     * Add pointsChangeLog
     *
     * @param PointsChangeLog $pointsChangeLog
     *
     * @return Lead
     */
    public function addPointsChangeLog(PointsChangeLog $pointsChangeLog)
    {
        $this->pointsChangeLog[] = $pointsChangeLog;

        return $this;
    }

    /**
     * Creates a points change entry
     *
     * @param           $type
     * @param           $name
     * @param           $action
     * @param           $pointsDelta
     * @param IpAddress $ip
     */
    public function stageChangeLogEntry($type, $name, $action)
    {
        //create a new points change event
        $event = new StagesChangeLog();
        $event->setEventName($name);
        $event->setActionName($action);
        $event->setDateAdded(new \DateTime());
        $event->setLead($this);
        $this->stageChangeLog($event);
    }

    /**
     * Add pointsChangeLog
     *
     * @param PointsChangeLog $pointsChangeLog
     *
     * @return Lead
     */
    public function stageChangeLog(StagesChangeLog $stageChangeLog)
    {
        $this->stageChangeLog[] = $stageChangeLog;

        return $this;
    }

    /**
     * Remove pointsChangeLog
     *
     * @param PointsChangeLog $pointsChangeLog
     */
    public function removePointsChangeLog(PointsChangeLog $pointsChangeLog)
    {
        $this->pointsChangeLog->removeElement($pointsChangeLog);
    }

    /**
     * Get pointsChangeLog
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPointsChangeLog()
    {
        return $this->pointsChangeLog;
    }

    /**
     * @param string $identifier
     *
     * @return $this
     */
    public function addPushIDEntry($identifier)
    {
        /** @var PushID $id */
        foreach ($this->pushIds as $id) {
            if ($id->getPushID() === $identifier) {
                return $this;
            }
        }

        $entity = new PushID();
        $entity->setPushID($identifier);
        $entity->setLead($this);

        $this->addPushID($entity);

        return $this;
    }

    /**
     * @param PushID $pushID
     *
     * @return $this
     */
    public function addPushID(PushID $pushID)
    {
        $this->pushIds[] = $pushID;

        return $this;
    }

    /**
     * @param PushID $pushID
     */
    public function removePushID(PushID $pushID)
    {
        $this->pushIds->removeElement($pushID);
    }

    /**
     * @return ArrayCollection
     */
    public function getPushIDs()
    {
        return $this->pushIds;
    }

    /**
     * @param DoNotContact $doNotContact
     *
     * @return $this
     */
    public function addDoNotContactEntry(DoNotContact $doNotContact)
    {
        $this->changes['dnc_channel_status'][$doNotContact->getChannel()] = [
            'reason'   => $doNotContact->getReason(),
            'comments' => $doNotContact->getComments()
        ];

        // @deprecated - to be removed in 2.0
        switch ($doNotContact->getReason()) {
            case DoNotContact::BOUNCED:
                $type = 'bounced';
                break;
            case DoNotContact::MANUAL:
                $type = 'manual';
                break;
            case DoNotContact::UNSUBSCRIBED:
            default:
                $type = 'unsubscribed';
                break;
        }
        $this->changes['dnc_status'] = [$type, $doNotContact->getComments()];

        $this->doNotContact[] = $doNotContact;

        return $this;
    }

    /**
     * @param DoNotContact $doNotContact
     */
    public function removeDoNotContactEntry(DoNotContact $doNotContact)
    {
        $this->changes['dnc_channel_status'][$doNotContact->getChannel()] = [
            'reason'     => DoNotContact::IS_CONTACTABLE,
            'old_reason' => $doNotContact->getReason(),
            'comments'   => $doNotContact->getComments()
        ];

        // @deprecated to be removed in 2.0
        $this->changes['dnc_status'] = ['removed', $doNotContact->getComments()];

        $this->doNotContact->removeElement($doNotContact);
    }

    /**
     * @return ArrayCollection
     */
    public function getDoNotContact()
    {
        return $this->doNotContact;
    }

    /**
     * Set internal storage
     *
     * @param $internal
     */
    public function setInternal($internal)
    {
        $this->internal = $internal;
    }

    /**
     * Get internal storage
     *
     * @return mixed
     */
    public function getInternal()
    {
        return $this->internal;
    }

    /**
     * Set social cache
     *
     * @param $cache
     */
    public function setSocialCache($cache)
    {
        $this->socialCache = $cache;
    }

    /**
     * Get social cache
     *
     * @return mixed
     */
    public function getSocialCache()
    {
        return $this->socialCache;
    }

    /**
     * @param $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @param bool $ungroup
     *
     * @return array
     */
    public function getFields($ungroup = false)
    {
        if ($ungroup && isset($this->fields['core'])) {
            $return = [];
            foreach ($this->fields as $group => $fields) {
                $return += $fields;
            }

            return $return;
        }

        return $this->fields;
    }

    /**
     * Get profile values
     *
     * @return array
     */
    public function getProfileFields()
    {
        $fieldValues = [
            'id' => $this->id
        ];
        if (isset($this->fields['core'])) {
            foreach ($this->fields as $group => $fields) {
                foreach ($fields as $alias => $field) {
                    $fieldValues[$alias] = $field['value'];
                }
            }
        }

        return array_merge($fieldValues, $this->updatedFields);
    }

    /**
     * Add an updated field to persist to the DB and to note changes
     *
     * @param        $alias
     * @param        $value
     * @param string $oldValue
     */
    public function addUpdatedField($alias, $value, $oldValue = '')
    {
        if ($this->wasAnonymous == null) {
            $this->wasAnonymous = $this->isAnonymous();
        }

        $value = trim($value);
        if ($value == '') {
            // Ensure value is null for consistency
            $value = null;
        }

        $this->changes['fields'][$alias] = [$oldValue, $value];
        $this->updatedFields[$alias]     = $value;
    }

    /**
     * Get the array of updated fields
     *
     * @return array
     */
    public function getUpdatedFields()
    {
        return $this->updatedFields;
    }

    /**
     * @return mixed
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param mixed $color
     */
    public function setColor($color)
    {
        $this->color = $color;
    }

    /**
     * @return bool
     */
    public function isAnonymous()
    {
        if (
        $name = $this->getName()
            || !empty($this->updatedFields['firstname'])
            || !empty($this->updatedFields['lastname'])
            || !empty($this->updatedFields['company'])
            || !empty($this->updatedFields['email'])
            || !empty($this->fields['core']['company']['value'])
            || !empty($this->fields['core']['email']['value'])
            || $socialIdentity = $this->getFirstSocialIdentity()
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @return bool
     */
    protected function getFirstSocialIdentity()
    {
        if (isset($this->fields['social'])) {
            foreach ($this->fields['social'] as $social) {
                if (!empty($social['value'])) {
                    return $social['value'];
                }
            }
        } elseif (!empty($this->updatedFields)) {
            foreach ($this->availableSocialFields as $social) {
                if (!empty($this->updatedFields[$social])) {
                    return $this->updatedFields[$social];
                }
            }
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function isNewlyCreated()
    {
        return $this->newlyCreated;
    }

    /**
     * @param boolean $newlyCreated
     */
    public function setNewlyCreated($newlyCreated)
    {
        $this->newlyCreated = $newlyCreated;
    }

    /**
     * @return mixed
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @param string $source
     *
     * @return void
     */
    public function setPreferredProfileImage($source)
    {
        $this->preferredProfileImage = $source;
    }

    /**
     * @return string
     */
    public function getPreferredProfileImage()
    {
        return $this->preferredProfileImage;
    }

    /**
     * @return mixed
     */
    public function getDateIdentified()
    {
        return $this->dateIdentified;
    }

    /**
     * @param mixed $dateIdentified
     */
    public function setDateIdentified($dateIdentified)
    {
        $this->dateIdentified = $dateIdentified;
    }

    /**
     * @return mixed
     */
    public function getLastActive()
    {
        return $this->lastActive;
    }

    /**
     * @param mixed $lastActive
     */
    public function setLastActive($lastActive)
    {
        $this->changes['dateLastActive'] = [$this->lastActive, $lastActive];
        $this->lastActive                = $lastActive;
    }

    /**
     * @param array $availableSocialFields
     */
    public function setAvailableSocialFields(array $availableSocialFields)
    {
        $this->availableSocialFields = $availableSocialFields;
    }

    /**
     * Add tag
     *
     * @param Tag $tag
     *
     * @return Lead
     */
    public function addTag(Tag $tag)
    {
        $this->isChanged('tags', $tag);
        $this->tags[$tag->getTag()] = $tag;

        return $this;
    }

    /**
     * Remove tag
     *
     * @param Tag $tag
     */
    public function removeTag(Tag $tag)
    {
        $this->isChanged('tags', $tag->getTag());
        $this->tags->removeElement($tag);
    }

    /**
     * Get tags
     *
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set tags
     *
     * @param $tags
     *
     * @return $this
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get tags
     *
     * @return mixed
     */
    public function getUtmTags()
    {
        return $this->utmtags;
    }

    /**
     * Set tags
     *
     * @param $tags
     *
     * @return $this
     */
    public function setUtmTags($utmTags)
    {
        $this->isChanged('utmtags', $utmTags);
        $this->utmtags[] = $utmTags;

        return $this;
    }

    /**
     * Set stage
     *
     * @param \Mautic\StageBundle\Entity\Stage $stage
     *
     * @return Stage
     */
    public function setStage(Stage $stage)
    {
        $this->stage = $stage;

        return $this;
    }

    /**
     * Get stage
     *
     * @return \Mautic\StageBundle\Entity\Stage
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     * Set stage
     *
     * @param FrequencyRule $frequencyRules
     *
     * @return frequencyRules
     */
    public function setFrequencyRules(FrequencyRule $frequencyRules)
    {
        $this->isChanged('frequencyRules', $frequencyRules);
        $this->frequencyRules[$frequencyRules->getId()] = $frequencyRules;

        return $this;
    }

    /**
     * Get stage
     *
     * @return array
     */
    public function getFrequencyRules()
    {
        return $this->frequencyRules;
    }

    /**
     * Remove frequencyRule
     *
     * @param Tag $tag
     */
    public function removeFrequencyRule(FrequencyRule $frequencyRule)
    {
        $this->isChanged('frequencyRule', $frequencyRule->getId());
        $this->frequencyRules->removeElement($frequencyRule);
    }

    /**
     * Get attribution value
     *
     * @return bool
     */
    public function getAttribution()
    {
        return (float) $this->getFieldValue('attribution');
    }

    /**
     * If there is an attribution amount but no date, insert today's date
     */
    public function checkAttributionDate()
    {
        $attribution     = $this->getFieldValue('attribution');
        $attributionDate = $this->getFieldValue('attribution_date');

        if (!empty($attribution) && empty($attributionDate)) {
            $this->addUpdatedField('attribution_date', (new \DateTime())->format('Y-m-d'));
        } elseif (empty($attribution)) {
            $this->addUpdatedField('attribution_date', null);
        }
    }

    /**
     * Set date identified
     */
    public function checkDateIdentified()
    {
        if ($this->dateIdentified == null && $this->wasAnonymous) {
            //check the changes to see if the user is now known
            if (!$this->isAnonymous()) {
                $this->dateIdentified            = new \DateTime();
                $this->changes['dateIdentified'] = ['', $this->dateIdentified];
            }
        }
    }
}
