<?php

namespace Redking\ParseBundle\ParseObject;

/**
 * Redking\ParseBundle\ParseObject\Installation
 */
abstract class Installation
{

    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @var string
     */
    protected $GCMSenderId;

    /**
     * @var string
     */    
    protected $deviceToken;

    /**
     * @var string
     */    
    protected $localeIdentifier;

    /**
     * @var integer
     */    
    protected $badge;

    /**
     * @var string
     */    
    protected $parseVersion;

    /**
     * @var string
     */    
    protected $appIdentifier;

    /**
     * @var string
     */    
    protected $appName;

    /**
     * @var boolean
     */    
    protected $isAcceptPush;

    /**
     * @var string
     */    
    protected $deviceType;

    /**
     * @var array
     */    
    protected $channels;

    /**
     * @var string
     */    
    protected $pushType;

    /**
     * @var string
     */    
    protected $installationId;

    /**
     * @var string
     */    
    protected $appVersion;

    /**
     * @var string
     */    
    protected $timeZone;

    /**
     * Get id
     *
     * @return string $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set gCMSenderId
     *
     * @param string $gCMSenderId
     * @return self
     */
    public function setGCMSenderId($gCMSenderId)
    {
        $this->GCMSenderId = $gCMSenderId;
        return $this;
    }

    /**
     * Get gCMSenderId
     *
     * @return string $gCMSenderId
     */
    public function getGCMSenderId()
    {
        return $this->GCMSenderId;
    }

    /**
     * Set deviceToken
     *
     * @param string $deviceToken
     * @return self
     */
    public function setDeviceToken($deviceToken)
    {
        $this->deviceToken = $deviceToken;
        return $this;
    }

    /**
     * Get deviceToken
     *
     * @return string $deviceToken
     */
    public function getDeviceToken()
    {
        return $this->deviceToken;
    }

    /**
     * Set localeIdentifier
     *
     * @param string $localeIdentifier
     * @return self
     */
    public function setLocaleIdentifier($localeIdentifier)
    {
        $this->localeIdentifier = $localeIdentifier;
        return $this;
    }

    /**
     * Get localeIdentifier
     *
     * @return string $localeIdentifier
     */
    public function getLocaleIdentifier()
    {
        return $this->localeIdentifier;
    }

    /**
     * Set badge
     *
     * @param integer $badge
     * @return self
     */
    public function setBadge($badge)
    {
        $this->badge = $badge;
        return $this;
    }

    /**
     * Get badge
     *
     * @return integer $badge
     */
    public function getBadge()
    {
        return $this->badge;
    }

    /**
     * Set parseVersion
     *
     * @param string $parseVersion
     * @return self
     */
    public function setParseVersion($parseVersion)
    {
        $this->parseVersion = $parseVersion;
        return $this;
    }

    /**
     * Get parseVersion
     *
     * @return string $parseVersion
     */
    public function getParseVersion()
    {
        return $this->parseVersion;
    }

    /**
     * Set appIdentifier
     *
     * @param string $appIdentifier
     * @return self
     */
    public function setAppIdentifier($appIdentifier)
    {
        $this->appIdentifier = $appIdentifier;
        return $this;
    }

    /**
     * Get appIdentifier
     *
     * @return string $appIdentifier
     */
    public function getAppIdentifier()
    {
        return $this->appIdentifier;
    }

    /**
     * Set appName
     *
     * @param string $appName
     * @return self
     */
    public function setAppName($appName)
    {
        $this->appName = $appName;
        return $this;
    }

    /**
     * Get appName
     *
     * @return string $appName
     */
    public function getAppName()
    {
        return $this->appName;
    }

    /**
     * Set isAcceptPush
     *
     * @param boolean $isAcceptPush
     * @return self
     */
    public function setIsAcceptPush($isAcceptPush)
    {
        $this->isAcceptPush = $isAcceptPush;
        return $this;
    }

    /**
     * Get isAcceptPush
     *
     * @return boolean $isAcceptPush
     */
    public function getIsAcceptPush()
    {
        return $this->isAcceptPush;
    }

    /**
     * Set deviceType
     *
     * @param string $deviceType
     * @return self
     */
    public function setDeviceType($deviceType)
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    /**
     * Get deviceType
     *
     * @return string $deviceType
     */
    public function getDeviceType()
    {
        return $this->deviceType;
    }

    /**
     * Set channels
     *
     * @param array $channels
     * @return self
     */
    public function setChannels($channels)
    {
        $this->channels = $channels;
        return $this;
    }

    /**
     * Get channels
     *
     * @return array $channels
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * Set pushType
     *
     * @param string $pushType
     * @return self
     */
    public function setPushType($pushType)
    {
        $this->pushType = $pushType;
        return $this;
    }

    /**
     * Get pushType
     *
     * @return string $pushType
     */
    public function getPushType()
    {
        return $this->pushType;
    }

    /**
     * Set installationId
     *
     * @param string $installationId
     * @return self
     */
    public function setInstallationId($installationId)
    {
        $this->installationId = $installationId;
        return $this;
    }

    /**
     * Get installationId
     *
     * @return string $installationId
     */
    public function getInstallationId()
    {
        return $this->installationId;
    }

    /**
     * Set appVersion
     *
     * @param string $appVersion
     * @return self
     */
    public function setAppVersion($appVersion)
    {
        $this->appVersion = $appVersion;
        return $this;
    }

    /**
     * Get appVersion
     *
     * @return string $appVersion
     */
    public function getAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * Set timeZone
     *
     * @param string $timeZone
     * @return self
     */
    public function setTimeZone($timeZone)
    {
        $this->timeZone = $timeZone;
        return $this;
    }

    /**
     * Get timeZone
     *
     * @return string $timeZone
     */
    public function getTimeZone()
    {
        return $this->timeZone;
    }
}
