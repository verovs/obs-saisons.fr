<?php

namespace App\DisplayData\Station;

use App\Entity\Event;
use App\Entity\EventSpecies;
use App\Entity\Individual;
use App\Entity\Observation;
use App\Entity\Species;
use App\Entity\Station;
use Doctrine\Common\Persistence\ManagerRegistry;

class StationSpeciesDisplayData
{
    private $manager;
    private $station;
    private $species;
    private $thisStationIndividuals;
    private $validEvents;
    private $stationSpeciesObservations;
    private $eventsSpecies;
    private $periods;
    private $allObsYears;

    /**
     * @var Observation
     */
    private $lastObservation;
    private $allIndividualsObservationsData;

    public function __construct(
        Station $station,
        Species $species,
        ManagerRegistry $manager,
        array $thisStationIndividuals = null,
        array $stationSpeciesObservations = null
    ) {
        $this->manager = $manager;
        $this->station = $station;
        $this->species = $species;

        if (null === $thisStationIndividuals) {
            $this->thisStationIndividuals = [];
            self::setStationSpeciesData();
        } else {
            $this->thisStationIndividuals = $thisStationIndividuals;
        }

        $this->validEvents = [];
        self::setValidEvents();

        if (null === $stationSpeciesObservations) {
            $this->stationSpeciesObservations = [];
            self::setStationObservations();
        } else {
            $this->stationSpeciesObservations = $stationSpeciesObservations;
        }

        $this->eventsSpecies = $manager->getRepository(EventSpecies::class)
            ->findBy(['species' => $species], ['species' => 'asc'])
        ;

        $this->periods = [];
        $this->allIndividualsObservationsData = [];

        self::setAllObsYears();
        self::setPeriods();
        self::setAllIndividualsObservationsDisplayData();
        self::setLastObservation();
    }

    private function setStationSpeciesData(): self
    {
        $allStationIndividualsData = $this->manager->getRepository(Individual::class)
            ->findSpeciesIndividualsForStation($this->station)
        ;
        foreach ($allStationIndividualsData as $stationIndividuals) {
            $species = $stationIndividuals->getSpecies();
            if ($species === $this->species) {
                $this->thisStationIndividuals[] = $stationIndividuals;
            }
        }

        return $this;
    }

    public function getStationSpeciesData(): array
    {
        return $this->thisStationIndividuals;
    }

    public function setValidEvents(): self
    {
        $eventsForSpecies = $this->manager->getRepository(EventSpecies::class)->findBy(['species' => $this->species]);
        foreach ($eventsForSpecies as $eventSpecies) {
            $this->validEvents[] = $eventSpecies->getEvent();
        }

        return $this;
    }

    private function setStationObservations(): self
    {
        $stationObservations = $this->manager->getRepository(Observation::class)
            ->findAllObsInStation($this->station)
        ;
        foreach ($stationObservations as $stationObservation) {
            if ($stationObservation->getSpecies() === $this->species && in_array($stationObservation->getEvent(), $this->validEvents)) {
                $this->stationSpeciesObservations[] = $stationObservation;
            }
        }
        uasort($this->stationSpeciesObservations, 'self::sortObsByDAte');

        return $this;
    }

    public function getSpecies(): Species
    {
        return $this->species;
    }

    private function setPeriods(): self
    {
        $loopStadeBbch = [];
        foreach ($this->eventsSpecies as $eventSpecies) {
            $event = $eventSpecies->getEvent();
            $eventName = Event::DISPLAY_LABELS[$event->getName()];
            $StadeBbch = $event->getStadeBbch();

            if (!in_array($eventName, array_keys($this->periods))) {
                $loopStadeBbch[] = $StadeBbch;
                $this->periods[$eventName] = [
                    'begin' => $eventSpecies->getStartDate(),
                    'end' => $eventSpecies->getEndDate(),
                ];
            }
            if (!in_array($StadeBbch, $loopStadeBbch)) {
                $loopStadeBbch[] = $StadeBbch;
                // the earliest begining date
                if ($eventSpecies->getStartDate() < $this->periods[$eventName]['begin']) {
                    $this->periods[$eventName]['begin'] = $eventSpecies->getStartDate();
                }
                // the latest ending date
                if ($eventSpecies->getEndDate() > $this->periods[$eventName]['end']) {
                    $this->periods[$eventName]['end'] = $eventSpecies->getEndDate();
                }
            }
        }

        return $this;
    }

    public function getPeriods(): array
    {
        return $this->periods;
    }

    private function filterStationSpeciesObservationsByIndividuals(Individual $individual)
    {
        $stationSpeciesObservationsByIndividuals = [];
        foreach ($this->stationSpeciesObservations as $observation) {
            $thisIndividual = $observation->getIndividual();
            if ($thisIndividual === $individual) {
                $stationSpeciesObservationsByIndividuals[] = $observation;
            }
        }

        return $stationSpeciesObservationsByIndividuals;
    }

    private function setAllIndividualsObservationsDisplayData(): self
    {
        foreach ($this->thisStationIndividuals as $individual) {
            $stationIndividualsDisplayData = new StationIndividualsDisplayData(
                $individual,
                $this->manager,
                $this->filterStationSpeciesObservationsByIndividuals($individual)
            );
            $this->allIndividualsObservationsData[] = $stationIndividualsDisplayData;
        }

        return $this;
    }

    public function getAllIndividualsObservationsDisplayData(): array
    {
        return $this->allIndividualsObservationsData;
    }

    public function getIndividualsCount(): int
    {
        return count($this->thisStationIndividuals);
    }

    public function getObsCount(): int
    {
        $obsCount = 0;
        foreach ($this->stationSpeciesObservations as $obs) {
            if (!$obs->getIsMissing()) {
                ++$obsCount;
            }
        }

        return $obsCount;
    }

    private function setAllObsYears(): self
    {
        $this->allObsYears = [];
        foreach ($this->stationSpeciesObservations as $obs) {
            $year = date_format($obs->getObsDate(), 'Y');
            if (!in_array($year, $this->allObsYears)) {
                $this->allObsYears[] = $year;
            }
        }

        return $this;
    }

    public function getAllObsYears(): array
    {
        return $this->allObsYears;
    }

    private function setLastObservation(): self
    {
        $this->lastObservation = reset($this->stationSpeciesObservations);

        return $this;
    }

    public function getLastObsDate(): \DateTimeInterface
    {
        return $this->lastObservation->getObsDate();
    }

    public function getLastObsStade(): string
    {
        return Event::DISPLAY_LABELS[$this->lastObservation->getEvent()->getName()];
    }

    private function sortObsByDAte(Observation $obsA, Observation $obsB)
    {
        return $obsB->getObsDate() <=> $obsA->getObsDate();
    }
}
