<?php

namespace HostMyDocs\Models;

use Psr\Log\LoggerInterface;

/**
 * Base model, need to be extended by other models
 *
 * @uses \JsonSerializable
 *
 * @ORM\MappedSuperclass
 */
abstract class BaseModel implements \JsonSerializable
{
    /**
     * @var string The database ID if exist (as string, cause fucking mongo doesn't implement serializable
     *
     * @Id
     * @Column(name= "id", type="integer")
     * @GeneratedValue
     */
    protected $id = null;

    /**
     * @var LoggerInterface Logger used by all sub models
     */
    protected $logger;

    /**
     * save the logger for models
     *
     * @param LoggerInterface $logger Logger used by all sub models
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getId()
    {
        return $this->id;
    }
}
