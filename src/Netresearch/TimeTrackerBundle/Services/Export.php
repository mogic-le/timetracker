<?php
/**
 * Netresearch Timetracker
 *
 * PHP version 5
 *
 * @category   Netresearch
 * @package    Timetracker
 * @subpackage Service
 * @author     Michael Lühr <michael.luehr@netresearch.de>
 * @author     Various Artists <info@netresearch.de>
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPl 3
 * @link       http://www.netresearch.de
 */

namespace Netresearch\TimeTrackerBundle\Services;

use Netresearch\TimeTrackerBundle\Entity\Entry as Entry;
use Netresearch\TimeTrackerBundle\Entity\TicketSystem;
use Netresearch\TimeTrackerBundle\Entity\User;
use Netresearch\TimeTrackerBundle\Helper\JiraOAuthApi;
use Netresearch\TimeTrackerBundle\Model\ExternalTicketSystem;
use Netresearch\TimeTrackerBundle\Entity\EntryRepository;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \chobie\Jira;

/**
 * Class Export
 *
 * @category   Netresearch
 * @package    Timetracker
 * @subpackage Service
 * @author     Michael Lühr <michael.luehr@netresearch.de>
 * @author     Various Artists <info@netresearch.de>
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPl 3
 * @link       http://www.netresearch.de
 */
class Export
{
    /**
     *  The value to determine if jira label is set or not.
     *
     * @var string
     */
    const IS_SET = 'X';

    /**
     * Label for indicating a bug
     *
     * @var string
     */
    const LABEL_BUG = 'NR_BUG';

    /**
     * Label for indicating a support issue
     *
     * @var string
     */
    const LABEL_SUPPORT = 'NR_SUPPORT';

    /**
     * Label for indicating a deployment issue
     *
     * @var string
     */
    const LABEL_DEPLOYMENT = 'Deployment';

    /**
     * Label for indicating a project issue
     *
     * @var string
     */
    const LABEL_PROJECT = 'NR_PROJECT';

    /**
     * Key for additional labels
     *
     * @var string
     */
    const LABEL_OTHER = 'MISC';

    /**
     * Label for indicating a issue with time problems
     *
     * @var string
     */
    const LABEL_FOO = 'NR_FOO';

    protected $container = null;

    protected $entriesRequireAddInfo = array();

    protected $additionalInformation = array();

    /**
     * @var ExternalTicketSystem[]
     */
    protected $ticketSystems = array();



    /**
     * mandatory dependency the service container
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }



    /**
     * Returns entries filtered and ordered.
     *
     * @param integer $userId     Filter entries by user
     * @param integer $year       Filter entries by year
     * @param integer $month      Filter entries by month
     * @param integer $projectId  Filter entries by project
     * @param array   $arSort     Sort result by given fields
     *
     * @return mixed
     */
    public function exportEntries($userId, $year, $month, $projectId, array $arSort = null)
    {
        $entriesRequireAdditionalInformation = $this->getEntriesRequireAddInfo($userId, $year, $month, $projectId);
        if (0 < count($entriesRequireAdditionalInformation)) {
            $this->extractTicketSystems($entriesRequireAdditionalInformation);
            $this->fetchAdditionalInfoFromExternalJira();
        }

        return $this->getEnrichedEntries($userId, $year, $month, $projectId, $arSort);
    }



    /**
     * Returns user name for given user ID.
     *
     * @param integer $userId User ID
     *
     * @return string $username - the name of the user or all if no valid user id is provided
     */
    public function getUsername($userId = null)
    {
        $username = 'all';
        if (0 < (int) $userId) {
            /* @var $user User */
            $user     = $this->container->get('doctrine')
                ->getRepository('NetresearchTimeTrackerBundle:User')
                ->find($userId);
            $username = $user->getUsername();
        }

        return $username;
    }



    /**
     * Returns entries which require additional information from external ticket
     * systems.
     *
     * @param integer $userId    Filter entries by user
     * @param integer $year      Filter entries by year
     * @param integer $month     Filter entries by month
     * @param integer $projectId Filter entries by project
     *
     * @return array[]
     */
    protected function getEntriesRequireAddInfo($userId, $year, $month, $projectId)
    {
        $arEntries = $this->getEntryRepository()->findByMonthWithExternalInformation(
            $userId, $year, $month, $projectId
        );

        foreach ($arEntries as $arEntry) {
            if (0 < strlen(trim($arEntry['ticket']))) {
                $this->additionalInformation[$arEntry['ticket']] = $arEntry;
            }
        }

        return $this->additionalInformation;
    }



    /**
     * Separates the work log entries by it's related ticket system.
     *
     * @param Entry[] $entries List of work log entries
     *
     * @return $this
     */
    protected function extractTicketSystems($entries)
    {
        foreach ($entries as $infoEntry) {
            if (!array_key_exists($infoEntry['id'], $this->ticketSystems)) {
                $this->ticketSystems[$infoEntry['id']]
                    = new ExternalTicketSystem($infoEntry['url'], $infoEntry['login'], $infoEntry['password']);
            }
            $this->ticketSystems[$infoEntry['id']]->addTicket($infoEntry['ticket']);
        }

        return $this;
    }



    /**
     * Removes issue keys from the issue array where the ticket number is 0,
     * e.g. WSD-0, TYPO-0 or SOME-0.
     *
     * @param array $arIssues a array of issue keys
     *
     * @return array filtered array
     */
    protected function filterInvalidIssues(array $arIssues)
    {
        $arIssues = array_combine($arIssues, $arIssues);
        $arIssuesReturn = $arIssues;
        foreach ($arIssues as $strKey) {
            // filter issues like SOME-0
            if (preg_match('/\-0$/', $strKey)) {
                unset($arIssuesReturn[$strKey]);
            }
        }

        return $arIssuesReturn;
    }



    /**
     * Fetches additional information from external Ticketsystem.
     *
     * @return void
     */
    protected function fetchAdditionalInfoFromExternalJira()
    {
        foreach ($this->ticketSystems as $ticketSystem) {
            $arTickets = $this->filterInvalidIssues(
                $ticketSystem->getTickets()
            );

            if (0 < count($arTickets)) {
                $auth   = new Jira\Api\Authentication\Basic(
                    $ticketSystem->getLogin(), $ticketSystem->getPassword()
                );
                $client = new Jira\Api($ticketSystem->getUrl(), $auth);
                $client->setOptions(null);
                $walker = new Jira\Issues\Walker($client);
                foreach ($arTickets as $strKey) {
                    try {
                        $walker->push('issueKey IN (' . $strKey . ')');
                    } catch (\Exception $e) {
                        // skip issues that do not exist
                        continue;
                    }
                    /** @var Jira\Issue $issue */
                    foreach ($walker as $issue) {
                        $arFields = $issue->getFields();
                        $this->additionalInformation[$issue->getKey()]['reporter']
                            = $arFields['reporter']['displayName'];
                        $this->additionalInformation[$issue->getKey()]['summary']
                            = $arFields['summary'];
                        $this->addLabelFieldsToAdditionalInformation(
                            $issue,
                            $arFields
                        );
                    }
                }
            }
        }
    }



    /**
     * Returns an array of JIRA tags which should be displayed in a single
     * column in the export.
     *
     * @return array
     */
    public function getLabelsForSingleColumns()
    {
        return array(
            self::LABEL_SUPPORT,
            self::LABEL_BUG,
            self::LABEL_PROJECT,
            self::LABEL_FOO,
            self::LABEL_DEPLOYMENT,
        );

    }



    /**
     * Adds information about labels to the additionalInformation data structure.
     *
     * <code>
     * array(
     *      'issue key' =>
     *          array( 'labels'
     *              array (
     *                  'label1' => '',
     *                  'label2' => 'X',
     *                  'label3' => '',
     *                  'MISC'   => 'label4,label5,label6',
     *              ),
     *          ),
     *      ),
     * )
     * </code>
     *
     * @param Jira\Issue $issue    Ticketsystem issue
     * @param array      $arFields Labels assigned to issue
     *
     * @return void
     */
    protected function addLabelFieldsToAdditionalInformation($issue, $arFields)
    {

        // get labels set in issues
        $arLabels = array_fill_keys(array_values($arFields['labels']), '');

        // get label columns
        $arLabelColumns = array_fill_keys(array_values($this->getLabelsForSingleColumns()), '');

        // add label columns with default value to additional information output
        $this->additionalInformation[$issue->getKey()]['labels']
            = $arLabelColumns;

        // set ISSET on label columns
        foreach ($arLabelColumns as $strLabel => $value) {
            if (! isset($arLabels[$strLabel])) {
                continue;
            }

            $this->additionalInformation[$issue->getKey()]['labels'][$strLabel]
                = self::IS_SET;

            unset($arLabels[$strLabel]);
        }

        // add all other labels to MISC column
        $this->additionalInformation[$issue->getKey()]['labels'][self::LABEL_OTHER]
            = implode(',', array_keys($arLabels));
    }



    /**
     * returns the entry repository
     *
     * @return EntryRepository
     */
    protected function getEntryRepository()
    {
        return $this->container->get('doctrine')->getRepository('NetresearchTimeTrackerBundle:Entry');
    }



    /**
     * Returns filtered and ordered work log entries enriched with additional
     * data from external ticket systems.
     *
     * @param integer $userId    Filter entries by user
     * @param integer $year      Filter entries by year
     * @param integer $month     Filter entries by month
     * @param integer $projectId Filter entries by project
     * @param array   $arSort    Sort result by given fields
     *
     * @return \Netresearch\TimeTrackerBundle\Entity\Entry[]
     */
    protected function getEnrichedEntries($userId, $year, $month, $projectId, array $arSort = null)
    {
        /** @var \Netresearch\TimeTrackerBundle\Entity\Entry[] $arEntries */
        $arEntries = $this->getEntryRepository()
            ->findByDate($userId, $year, $month, $projectId, $arSort);

        foreach ($arEntries as $entry) {
            if (array_key_exists($entry->getTicket(), $this->additionalInformation)
                && array_key_exists('reporter', $this->additionalInformation[$entry->getTicket()])
            ) {
                $arAdditionalInformation
                    = $this->additionalInformation[$entry->getTicket()];
                $entry->setExternalReporter(
                    $arAdditionalInformation['reporter']
                );
                $entry->setExternalSummary(
                    $arAdditionalInformation['summary']
                );

                $entry->setExternalLabels(
                    $arAdditionalInformation['labels']
                );
            }
        }

        return $arEntries;
    }


    /**
     * Adds billable (boolean) property to entries depending on the existence
     * of a "billable" label in belonging JIRA issues
     *
     * @param int   $currentUserId
     * @param array $entries
     *
     * @return array
     */
    public function enrichEntriesWithBillableInformation($currentUserId, array $entries)
    {
        /* @var $currentUser \Netresearch\TimeTrackerBundle\Entity\User */
        $doctrine = $this->container->get('doctrine');
        $currentUser = $doctrine->getRepository('NetresearchTimeTrackerBundle:User')
            ->find($currentUserId);

        /** @var Router $router */
        $router = $this->container->get('router');

        $arTickets = [];
        $arApi = [];
        /** @var Entry $entry */
        foreach ($entries as $entry) {
            if (strlen($entry->getTicket()) > 0
                && $entry->getProject()
                && $entry->getProject()->getTicketSystem()
                && $entry->getProject()->getTicketSystem()->getBookTime()
                && $entry->getProject()->getTicketSystem()->getType() == 'JIRA'
            ) {
                /** @var TicketSystem $ticketSystem */
                $ticketSystem = $entry->getProject()->getTicketSystem();

                if (!isset($arApi[$ticketSystem->getId()])) {
                    $arApi[$ticketSystem->getId()] = new JiraOAuthApi(
                        $currentUser,
                        $ticketSystem,
                        $doctrine,
                        $router
                    );
                }

                if (!isset($arTickets[$ticketSystem->getId()][$entry->getTicket()])) {
                    $arTickets[$ticketSystem->getId()][] = $entry->getTicket();
                }
            }
        }

        $arBillable = [];
        /** @var JiraOAuthApi $jiraApi */
        foreach ($arApi as $idx => $jiraApi) {
            $ret = $jiraApi->get(
                '/search?' . http_build_query(
                    [
                        'jql' => 'IssueKey in (' . join(',', $arTickets[$idx]) . ')',
                        'fields' => 'labels'
                    ]
                )
            );

            foreach ($ret->issues as $issue) {
                if (isset($issue->fields->labels) && in_array('billable', $issue->fields->labels)) {
                    $arBillable[] = $issue->key;
                }
            }
        }

        foreach ($entries as $entry) {
            $entry->billable = in_array($entry->getTicket(), $arBillable);
        }

        return $entries;
    }
}
