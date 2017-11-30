<?php

namespace Bolt\Extension\Vibby\Alps\Command;

use Bolt\Legacy\Content;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Bolt\Nut\BaseCommand;

class ImportCommand extends BaseCommand
{
    public $db;
    public $app;

    protected function configure()
    {
        $this->setName('import:articles')
            ->setDescription('This command imports content from a CSV format file.')
            ->setHelp('')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'CSV File to import from',
                dirname(__FILE__).'/../../../../../../app/config/import.csv'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $contentTypes = ['pages', 'articles', 'planning', 'livres', 'liens', 'evenements', 'predications'];
        foreach ($contentTypes as $contentType) {
            $existings = $this->app['storage']->getContent($contentType);
            if (!$existings) {
//                throw new \Exception(sprintf('Cannot find content type «%s»', $contentType));
            }
            foreach ($existings as $existing) {
                $this->app['storage']->deleteContent($contentType, $existing['id']);
            }
        }

        $file = $input->getOption('file');
        $pages = $this->parseCsv($file);
        $planningDone = [];
        foreach ($pages as $page) {
            /** @var Content $record */
            preg_match('#\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}#', $page['Date'], $matches);
            $dateSource = $matches ? $matches[0] : $page['Date'];
            $date = new \DateTime($dateSource);
            preg_match('#<a href="/?([^/]*)?/([^"]*)">(.*)</a>#', $page['Titre'], $matches);
            list($fullMatch, $type, $slug, $title) = $matches;
            if ($type == 'planning') {
                if (in_array($date->format('Y-m-d'), $planningDone)) {
                    continue;
                }
                $planningDone[] = $date->format('Y-m-d');
                if (isset($page['Autres'])) {
                    $this->insertDates($page['Autres'], $date);
                }
                $record = $this->app['storage']->getEmptyContent('planning');
                $record->setValues([
                    'title' => $date->format('Y-m-d'),
                    'slug' => $date->format('Y-m-d'),
                    'status' => 'published',
                    'datepublish' => $date ? $date->format('Y-m-d 00:00:00') : null,
                    'status' => 'published',
                    'elders' => isset($page['Anciens référents']) ? $page['Anciens référents'] : null,
                    'afternoon' => isset($page['Après-midi']) ? $page['Après-midi'] : null,
                    'sabbath_school_mission' => isset($page['Catéchèse Bulletin']) ? $page['Catéchèse Bulletin'] : null,
                    'sabbath_school_prayer' => isset($page['Catéchèse Prière']) ? $page['Catéchèse Prière'] : null,
                    'sabbath_school_presidence' => isset($page['Catéchèse Présidence']) ? $page['Catéchèse Présidence'] : null,
                    'pray_cell' => isset($page['Cellule de prière']) ? $page['Cellule de prière'] : null,
                    'sing_leader' => isset($page['Conducteur chant']) ? $page['Conducteur chant'] : null,
                    'deacon' => isset($page['Diaconat']) ? $page['Diaconat'] : null,
                    'slideshow' => isset($page['Diaporamas']) ? $page['Diaporamas'] : null,
                    'presidence' => isset($page['Estrade']) ? $page['Estrade'] : null,
                    'kids_story' => isset($page['Histoire enfants']) ? $page['Histoire enfants'] : null,
                    'bible_reader' => isset($page['Lecture biblique']) ? $page['Lecture biblique'] : null,
                    'belief_reader' => isset($page['Lecture croyances']) ? $page['Lecture croyances'] : null,
                    'musician' => isset($page['Musique']) ? $page['Musique'] : null,
                    'cleaner' => isset($page['Ménage']) ? $page['Ménage'] : null,
                    'pastoral_pray' => isset($page['Prière pastorale']) ? $page['Prière pastorale'] : null,
                    'predication' => isset($page['Prédication']) ? $page['Prédication'] : null,
                    'pray_meeting' => isset($page['Réunion de prière']) ? $page['Réunion de prière'] : null,
                    'sonorisation' => isset($page['Sonorisation']) ? $page['Sonorisation'] : null,
                    'special' => isset($page['Spécial']) ? $page['Spécial'] : null,
                ]);
                $record->set('datecreated', $date ? $date->format('Y-m-d 00:00:00') : null);
            } else {
                if (!$title) {
                    die('stop');
                }
                $type = ($type == 'videos') ? 'predication' : $type;
                if ($type == 'videos') {
                    $type = 'predication';
                }
                if ($type == 'demande') {
                    continue;
                }
                if ($type == 'newsletter') {
                    continue;
                }
                $record = $this->app['storage']->getEmptyContent(($type) ? $type : 'pages');
                preg_match('#src="https://www.nantes-adventiste.com/sites/default/files/([^"]*)".*( alt="([^"]*)")?#', $page['Image'], $matches);
                $imagePath = isset($matches[1]) ? $matches[1] : null;
                $imageAlt = isset($matches[3]) ? $matches[3] : null;
                preg_match('#"(https://www.youtube.[^"]*)"#', isset($page['Vidéo']) ? $page['Vidéo'] : null, $matches);
                $videoUrl = isset($matches[1]) ? $matches[1] : null;
                $record->setValues([
                    'title' => $title,
                    'slug' => $slug,
                    'date' => $date ? $date->format('Y-m-d 00:00:00') : null,
                    'body' => $page['Contenu'],
                    'video' => serialize([
                        'url' => $videoUrl,
                        'width' => 480,
                        'height' => 270,
                        'authorname' => 'Eglise adventiste du 7e Jour de Nantes',
                        'title' => $title,
                    ]),
                    'image' => serialize([
                        'file' => $imagePath,
                        'alt' => $title,
                    ]),
                    'status' => 'published',
                    'datepublish' => $date ? $date->format('Y-m-d 00:00:00') : null,
                    'status' => 'published',
                ]);
                $record->set('datecreated', $date ? $date->format('Y-m-d 00:00:00') : null);
                if ($page['Type'] == 'Média') {
                    $record->setTaxonomy('pages', 'media');
                }
                if ($page['Type'] == 'Article') {
                    $record->setTaxonomy('pages', 'news');
                }
            }

            $this->app['storage']->saveContent($record);
            $output->writeln('<info>Successfully saved page: '.$record['title'].'</info>');
        }
    }

    private function insertDates($otherContent, $dateSabbath)
    {
        $newContent = '';
        $date = null;
        dump($otherContent);
        foreach(preg_split("/<[^>]>/", $otherContent) as $line){
            $line = strip_tags($line);
            if (!$line) {
                continue;
            }
            preg_match('#(\d{1,2}) (janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)#i', $line, $match);
            dump($line);
            if (isset($match[0])) {
                if ($newContent && $date) {
                    $record = $this->app['storage']->getEmptyContent('planning');
                    $record->setValues([
                        'title' => $date->format('Y-m-d'),
                        'slug' => $date->format('Y-m-d'),
                        'status' => 'published',
                        'datepublish' => $date ? $date->format('Y-m-d 00:00:00') : null,
                        'status' => 'published',
                        'other' => $newContent,
                    ]);
                    $this->app['storage']->saveContent($record);
                    dump($record->getTitle());
                }
                list($full, $dom, $month) = $match;
                $month = strtr(
                    $month,
                    [
                        'janvier' => 1,
                        'février' => 2,
                        'mars' => 3,
                        'avril' => 4,
                        'mai' => 5,
                        'juin' => 6,
                        'juillet' => 7,
                        'août' => 8,
                        'septembre' => 9,
                        'octobre' => 10,
                        'novembre' => 11,
                        'décembre' => 12,
                    ]
                );
                $year = $dateSabbath->format('Y');
                if ($month = 1 && $dateSabbath->format('n') == 12) {
                    $year++;
                }
                if ($month = 12 && $dateSabbath->format('n') == 1) {
                    $year--;
                }
                $date = new \DateTime(sprintf('%d-%d-%d', $year, $month, $dom));
                $newContent = '';
            } else {
                $newContent .= $line."\n";
            }
        }
        if ($newContent && $date) {
            dump($newContent);
            $record = $this->app['storage']->getEmptyContent('planning');
            $record->setValues([
                'title' => $date->format('Y-m-d'),
                'slug' => $date->format('Y-m-d'),
                'status' => 'published',
                'datepublish' => $date ? $date->format('Y-m-d 00:00:00') : null,
                'status' => 'published',
                'other' => $newContent,
            ]);
            $this->app['storage']->saveContent($record);
            dump($record->getTitle());
        }
    }

    protected function parseCsv($file)
    {
        ini_set('auto_detect_line_endings', '1');
        mb_internal_encoding('utf8');
        $rows = [];
        $file = new \SplFileObject($file);
        $file->setFlags(\SplFileObject::DROP_NEW_LINE);
        $file->setFlags(\SplFileObject::READ_AHEAD);
        $file->setFlags(\SplFileObject::SKIP_EMPTY);
        $file->setFlags(\SplFileObject::READ_CSV);
        $headers = false;
        while (!$file->eof()) {
            if (!$headers) {
                $headers = $file->fgetcsv("\t");
            }
            $row = $file->fgetcsv("\t");
            if (count(array_filter($row))) {
                $rows[] = array_combine(array_slice($headers, 0, count($row)), $row);
            }
        }

        return $rows;
    }

}
