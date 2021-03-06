<?php
/**
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;

/**
 * DoNotContactRepository.
 */
class DoNotContactRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * Get a list of DNC entries based on channel and lead_id.
     *
     * @param Lead   $lead
     * @param string $channel
     *
     * @return \Mautic\LeadBundle\Entity\DoNotContact[]
     */
    public function getEntriesByLeadAndChannel(Lead $lead, $channel)
    {
        return $this->findBy(['channel' => $channel, 'lead' => $lead]);
    }

    /**
     * @param null            $channel
     * @param null            $ids
     * @param null            $reason
     * @param null            $listId
     * @param ChartQuery|null $chartQuery
     *
     * @return array|int
     */
    public function getCount($channel = null, $ids = null, $reason = null, $listId = null, ChartQuery $chartQuery = null)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count(dnc.id) as dnc_count')
            ->from(MAUTIC_TABLE_PREFIX.'lead_donotcontact', 'dnc');

        if ($ids) {
            if (!is_array($ids)) {
                $ids = [(int) $ids];
            }
            $q->where(
                $q->expr()->in('dnc.channel_id', $ids)
            );
        }

        if ($channel) {
            $q->andWhere('dnc.channel = :channel')
                ->setParameter('channel', $channel);
        }

        if ($reason) {
            $q->andWhere('dnc.reason = :reason')
                ->setParameter('reason', $reason);
        }

        if ($listId) {
            $q->leftJoin('dnc', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'cs', 'cs.lead_id = dnc.lead_id');

            if (true === $listId) {
                $q->addSelect('cs.leadlist_id')
                    ->groupBy('cs.leadlist_id');
            } else {
                $q->andWhere('cs.leadlist_id = :list_id')
                    ->setParameter('list_id', $listId);
            }
        }

        if ($chartQuery) {
            $chartQuery->applyDateFilters($q, 'date_added', 'dnc');
        }

        $results = $q->execute()->fetchAll();

        if (true === $listId) {
            // Return list group of counts
            $byList = [];
            foreach ($results as $result) {
                $byList[$result['leadlist_id']] = $result['dnc_count'];
            }

            return $byList;
        }

        return (isset($results[0])) ? $results[0]['dnc_count'] : 0;
    }

    /**
     * @param       $leadId
     * @param array $options
     */
    public function getTimelineStats($leadId, array $options = [])
    {
        $query = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $query->select('dnc.channel, dnc.channel_id, dnc.date_added, dnc.reason, dnc.comments')
            ->from(MAUTIC_TABLE_PREFIX.'lead_donotcontact', 'dnc')
            ->where($query->expr()->eq('dnc.lead_id', (int) $leadId));

        if (isset($options['search']) && $options['search']) {
            $query->andWhere(
                $query->expr()->like('dnc.channel', $query->expr()->literal('%'.$options['search'].'%'))
            );
        }

        return $this->getTimelineResults($query, $options, 'dnc.channel', 'dnc.date_added', [], ['date_added']);
    }

    /**
     * @param      $channel
     * @param null $contacts Array of contacts to filter by
     *
     * @return array
     */
    public function getChannelList($channel, $contacts = null)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->select('l.id')
            ->from(MAUTIC_TABLE_PREFIX.'lead_donotcontact', 'dnc')
            ->leftJoin('dnc', MAUTIC_TABLE_PREFIX.'leads', 'l', 'l.id = dnc.lead_id')
            ->where('dnc.channel = :channel')
            ->setParameter('channel', $channel);

        if ($contacts) {
            $q->andWhere(
                $q->expr()->in('l.id', $contacts)
            );
        }

        $results = $q->execute()->fetchAll();

        $dnc = [];

        foreach ($results as $r) {
            $dnc[] = $r['id'];
        }

        unset($results);

        return $dnc;
    }
}
