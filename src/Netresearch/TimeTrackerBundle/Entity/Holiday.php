<?php

namespace Netresearch\TimeTrackerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Netresearch\TimeTrackerBundle\Model\Base as Base;

/**
 * Netresearch\TimeTrackerBundle\Entity\Holiday
 *
 * @ORM\Entity(repositoryClass="Netresearch\TimeTrackerBundle\Repository\HolidayRepository")
 * @ORM\Table(name="holidays")
 */
class Holiday extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=10)
     */
    private $day;

    /**
     * @var string $name
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private $name;


    public function __construct($day, $name)
    {
        $this->setDay($day);
        $this->name = $name;
    }

    /**
     * Set day
     *
     * @param string|\DateTime $day
     *
     * @return $this
     */
    public function setDay($day)
    {
        if ($day instanceof \DateTime) {
            $day = $day->format('Y-m-d');
        }

        $this->day = $day;
        return $this;
    }

    /**
     * Get day
     *
     * @return string
     */
    public function getDay()
    {
        return $this->day;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get array representation of holiday object
     *
     * @return array
     */
    public function toArray()
    {
        $day = $this->getDay();
        if ($day) {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $day);
            $day = $dateObj ? $dateObj->format('d/m/Y') : $day;
        }
        return array(
            'day'         => $day,
            'description' => $this->getName()
        );
    }

}
