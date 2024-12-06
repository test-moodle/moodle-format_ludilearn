<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace format_ludimoodle\local\adaptation;

use Exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Hexad scores class.
 *
 * @package          format_ludimoodle
 * @copyright        2023 Pimenko <support@pimenko.com><pimenko.com>
 * @authors          Jordan Kesraoui, Nihal Ouherrou
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hexad_scores {
    /**
     * @var float
     */
    public float $achiever;
    /**
     * @var float
     */
    public float $player;
    /**
     * @var float
     */
    public float $socialiser;
    /**
     * @var float
     */
    public float $freeSpirit;
    /**
     * @var float
     */
    public float $disruptor;
    /**
     * @var float
     */
    public float $philanthropist;

    /**
     * Constructor
     *
     * @param float $achiever
     * @param float $player
     * @param float $socialiser
     * @param float $freeSpirit
     * @param float $disruptor
     * @param float $philanthropist
     */
    public function __construct(float $achiever, float $player, float $socialiser, float $freeSpirit, float $disruptor, float $philanthropist) {
        $this->achiever = $achiever;
        $this->player = $player;
        $this->socialiser = $socialiser;
        $this->freeSpirit = $freeSpirit;
        $this->disruptor = $disruptor;
        $this->philanthropist = $philanthropist;
    }

    /**
     * Retrieve Hexad scores from the database based on the user's questionnaire responses
     *
     * @param int $userId User ID
     * @return hexad_scores Hexad scores
     */
    public static function fromDatabase(int $userId): hexad_scores {
        global $DB;

        // Define the question correspondences for each Hexad type
        $questionCorrespondences = [
            "achiever" => [5, 6],
            "player" => [9, 12],
            "socialiser" => [2, 4],
            "freeSpirit" => [7, 10],
            "disruptor" => [8, 11],
            "philanthropist" => [1, 3]
        ];

        // Initialize the Hexad scores
        $hexadScores = new hexad_scores(0, 0, 0, 0, 0, 0);

        // Retrieve the scores for each Hexad type
        foreach ($questionCorrespondences as $hexadType => $questionIds) {
            $questionIdsString = implode(',', $questionIds);

            $query = "SELECT questionid, score
                FROM {ludimoodle_answers}
                WHERE userid = :userid
                AND questionid IN ($questionIdsString)";
            $params = ['userid' => $userId];
            $scores = $DB->get_records_sql($query, $params);

            foreach ($scores as $score) {
                $questionScore = intval($score->score);
                // Add the score of each question to the total of the corresponding profile
                $hexadScores->{$hexadType} += $questionScore;
            }
        }

        // Initialize a new HexadScores object with the calculated total scores
        return new hexad_scores(
            $hexadScores->achiever,
            $hexadScores->player,
            $hexadScores->socialiser,
            $hexadScores->freeSpirit,
            $hexadScores->disruptor,
            $hexadScores->philanthropist
        );
    }

    // Get the score for a specific Hexad type

    /**
     * Get the score for a specific Hexad type
     *
     * @param string $typeName Hexad type name
     * @return float Hexad score
     * @throws Exception
     */
    public function getValue(string $typeName): float {
        $typeName = strtolower($typeName);
        return match ($typeName) {
            "achiever" => $this->achiever,
            "player" => $this->player,
            "socialiser" => $this->socialiser,
            "freespirit" => $this->freeSpirit,
            "disruptor" => $this->disruptor,
            "philanthropist" => $this->philanthropist,
            default => throw new Exception("Type not found in Hexad Data: " . $typeName),
        };
    }
}