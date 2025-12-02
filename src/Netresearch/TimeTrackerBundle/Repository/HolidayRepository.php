<?php

namespace Netresearch\TimeTrackerBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Netresearch\TimeTrackerBundle\Entity\Holiday;

class HolidayRepository extends EntityRepository
{
    /**
     * get all holidays in a given year and month
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function findByMonth($year, $month)
    {
        $em = $this->getEntityManager();

        $pattern = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . '%';

        $query = $em->createQuery(
            'SELECT h FROM NetresearchTimeTrackerBundle:Holiday h'
            . ' WHERE h.day LIKE :month'
            . ' ORDER BY h.day ASC'
        )->setParameter('month', $pattern);

        return $query->getResult();
    }

    /**
     * Get all holidays as array for admin interface
     *
     * @return array
     */
    public function getAllHolidays()
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            'SELECT h FROM NetresearchTimeTrackerBundle:Holiday h'
            . ' ORDER BY h.day DESC'
        );

        $holidays = $query->getResult();
        $result = [];

        /** @var Holiday $holiday */
        foreach ($holidays as $holiday) {
            $result[] = [
                'holiday' => [
                    'day' => $holiday->getDay(),
                    'name' => $holiday->getName(),
                ]
            ];
        }

        return $result;
    }

    /**
     * Find holiday by date
     *
     * @param string|\DateTime $day Date in Y-m-d format or DateTime object
     * @return Holiday|null
     */
    public function findByDay($day)
    {
        if ($day instanceof \DateTime) {
            $day = $day->format('Y-m-d');
        }

        return $this->find($day);
    }
}

