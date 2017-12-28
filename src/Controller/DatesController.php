<?php

namespace Bolt\Extension\Vibby\Alps\Controller;

use Bolt\Controller\Base;
use Bolt\Storage\Entity\Content;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Repository\ContentRepository;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Response;

class DatesController extends Base
{
    /**
     * {@inheritdoc}
     */
    public function addRoutes(ControllerCollection $c)
    {
        $c->match('/', [$this, 'callbackDatesCatching']);

        return $c;
    }

    public function callbackDatesCatching()
    {
        ////////////////////////////
        /// Parameters
        ////////////////////////////
        $fileUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQkKt317pFLxvJDcjvIfVc1mMm2lyQ7F7voNd5RGSQ_8GCRt8Oiyt5prdsIaYdm0o63gOf_vbqWGlW5/pub?output=tsv';
        $delimiter = "\t";
        $direction = 'horizontal';
        $positionForDate = 1;
        $fieldsConversion = [
            'cleaner' => 2,
            'elders' => 3,
            'deacon' => 4,
            'slideshow' => 5,
            'sabbath_school_presidency' => 6,
            'sabbath_school_prayer' => 7,
            'sabbath_school_mission' => 8,
            'sound_booth' => 9,
            'musician' => 10,
            'announcement' => 11,
            'sing_leader' => 12,
            'kids_story' => 13,
            'belief_reader' => 14,
            'bible_reader' => 15,
            'pastoral_pray' => 16,
            'presidency' => 17,
            'predication' => 18,
            'pray_cell' => 19,
            'afternoon' => 20,
        ];
        $otherDates = [
            'next sunday' => [
                'other' => 23,
            ],
            'next wednesday' => [
                'pray_meeting' => 25,
            ],
        ];
        ////////////////////////////
        /// End parameters
        ////////////////////////////

        $data = file_get_contents($fileUrl);
        $rows = explode("\n",$data);
        $s = [];
        foreach($rows as $row) {
            $s[] = str_getcsv($row, $delimiter);
        }
        if ($direction == 'horizontal') {
            $perDates = [];
            foreach($s as $kr => $r) {
                foreach ($r as $kt => $t) {
                    $perDates[$kt][$kr] = $t;
                }
            }
        } else {
            $perDates = $s;
        }
        $dates = [];
        $months = [
            'zero',
            'janvier',
            'février',
            'mars',
            'avril',
            'mai',
            'juin',
            'juillet',
            'août',
            'septembre',
            'octobre',
            'novembre',
            'décembre',
        ];
        foreach($perDates as $perDate) {
            if (!$perDate[$positionForDate]) {
                continue;
            }
            preg_match('#(\d+)-([^.]+)\.? (\d{4})#', $perDate[$positionForDate], $matches);
            $monthKey = null;
            foreach ($months as $monthKey => $month) {
                if (substr($month, 0, strlen($matches[2])) === $matches[2]) {
                    break;
                }
            }
            $dateS = sprintf('%d-%d-%d', $matches[3], $monthKey, $matches[1]);
            $dateTs = strtotime($dateS);
            $dateT = new \DateTime();
            $dateT->setTimestamp($dateTs);
            $date = [
                'title' => $dateT->format('Y-m-d'),
                'slug' => $dateT->format('Y-m-d'),
                'datepublish' => null,
                'status' => 'published',
            ];
            foreach ($fieldsConversion as $fieldName => $sourceId) {
                if ($perDate[$sourceId] && strlen($perDate[$sourceId]) > 2) {
                    $date[$fieldName] = ucwords(mb_strtolower($perDate[$sourceId]), ' ,.');
                }
            }
            $dates[$dateT->format('Y-m-d')] = $date;

            foreach ($otherDates as $diff => $otherDate) {
                $dateT->modify($diff);
                $date = [
                    'title' => $dateT->format('Y-m-d'),
                    'slug' => $dateT->format('Y-m-d'),
                    'datepublish' => null,
                    'status' => 'published',
                ];
                foreach ($otherDate as $fieldName => $sourceId) {
                    if ($perDate[$sourceId] && strlen($perDate[$sourceId]) > 2) {
                        $date[$fieldName] = ucwords(mb_strtolower($perDate[$sourceId]), ' ,.');
                    }
                }
                $dates[$dateT->format('Y-m-d')] = $date;
            }
        }
        foreach ($dates as $date) {
            dump($date);
            /** @var EntityManager $storage */
            $storage = $this->app['storage'];
            /** @var ContentRepository $repo */
            $repo = $storage->getRepository('planning');
            /** @var Content $record */
            $record = $repo->findOneBy(['slug' => $date['slug']]);
            if ($record) {
                $record->setValues($date);
                $repo->save($record);
            } else {
                $record = $storage->getEmptyContent('planning');
                $record->setValues($date);
                $this->app['storage']->saveContent($record);
            }
        }

        return new Response('Imported !', Response::HTTP_OK);
    }
}
