<?php

namespace HostMyDocs\Models;

use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

/**
 * Model representing a programming language of a project
 *
 * @Entity @Table(name="languages")
 * @HasLifecycleCallbacks
 */
class Language extends BaseModel
{
    /**
     * the version associated to this language
     *
     * @ManyToOne(targetEntity=HostMyDocs\Models\Version::class, cascade={"all"}, inversedBy="id")
     */
    private $version;

    /**
     * @var null|string Name of the programming language
     *
     * @Column(type="string")
     */
    private $name = null;

    /**
     * @var string uuid of the documentation
     *
     * @Column(type="string")
     */
    private $uuid = null;

    /**
     * @var string[] rootPaths for the docs and archives
     */
    private $rootPaths = [];

    /**
     * create a language
     *
     * @param LoggerInterface $logger Logger used to write logs
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->rootPaths = ['storageRoot' => "", 'archiveRoot' => ""];
    }

    /**
     * Build a JSON serializable array
     *
     * @return array an array containing the informations about this object for JSON serialization
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->uuid !== null) {
            $data['indexPath'] = $this->rootPaths['storageRoot'] . "/" . $this->uuid . "/index.html";
            $data['archivePath'] = $this->rootPaths['archiveRoot'] . "/" . $this->uuid . ".zip";
        }

        return $data;
    }

    public function setRootPaths(array $rootPaths)
    {
        if (count($rootPaths) === 2 && isset($rootPaths["storageRoot"]) && isset($rootPaths["archiveRoot"])) {
            $this->rootPaths = $rootPaths;
        }

        return $this;
    }

    /**
     * Get the version associated to this language
     *
     * @return Version the version associated to this language
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set the version associated to this language
     *
     * @param Version $version the new version
     *
     * @return Language this language
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Set the value of Uuid
     *
     * @param string $uuid
     *
     * @return Language this language
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }


    /**
     * Get the value of Uuid
     *
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get the name of the language
     *
     * @return null|string the name of the language
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the name of this Language if it is valid
     *
     * @param null|string $name the new value for the name
     * @param bool $allowEmpty whether the empty string ("") is allowed
     *
     * @return null|Language this Language if $name is valid, null otherwise
     */
    public function setName(?string $name, bool $allowEmpty = false): ?self
    {
        if ($name === null) {
            $this->logger->info('language name cannot be null');
            return null;
        }

        if (strpos($name, '/') !== false) {
            $this->logger->info('language name cannot contains slashes');
            return null;
        }

        if (strlen($name) === 0 && !$allowEmpty) {
            $this->logger->info('language name cannot be empty');
            return null;
        }

        $this->name = $name;

        return $this;
    }
}
