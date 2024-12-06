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

use format_ludimoodle\manager;
use stdClass;

defined('MOODLE_INTERNAL') || die();
/**
 * Suggestion output class.
 *
 * @package          format_ludimoodle
 * @copyright        2023 Pimenko <support@pimenko.com><pimenko.com>
 * @authors          Jordan Kesraoui, Nihal Ouherrou
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggestion_output {

    /**
     * Generate a suggestion based on affinities.
     *
     * @param int $userid The user for whom the suggestion is generated.
     * @param int | null $courseid The course for which the suggestion is generated. Default is null.
     * @return array An array containing the suggestion and sorted combined scores.
     */
    public static function generateSuggestionBasedOnAffinities(int $userid, int $courseid = null): array {
        global $DB;

        // Use static values.
        $file = file_get_contents(static_values::hexadAffinityMatrix());
        $staticRulesHex = json_decode($file, true);
        $hScores = hexad_scores::fromDatabase($userid);

        // Generate suggestion matrices based on user profiles and affinities
        $sugMatHex = self::matrixSuggestion($hScores, $staticRulesHex);
        // Sort the sugMatHex and sugMatMot arrays based on values in descending order
        arsort($sugMatHex);


        // Initialize an empty array to store combined scores
        $combinedScores = array();

        // Iterate over the sorted sugMatHex array
        foreach ($sugMatHex as $itemHexKey => $itemHexValue) {
            // Get the scores for the common item
            $hexadScore = $itemHexValue;
            // Store the combined score in the combinedScores array
            $combinedScores[$itemHexKey] = $hexadScore;
        }

        // Check if the combinedScores array is empty
        if (empty($combinedScores)) {
            // Handle the case where combinedScores is empty
            // For example, return a default value or generate an alternative suggestion
            $suggestion = null;
        } else {
            // Sort the combinedScores array based on values in descending order
            arsort($combinedScores);
            // Get the first item (highest score) from the sorted array
            $suggestion = array_key_first($combinedScores);
        }

        // Convert the combinedScores array to JSON string
        $combinedAffinitiesJson = json_encode($combinedScores);
        $manager = new manager();
        $gameelements = $manager->get_gameelements_auto($suggestion, $courseid);
        $profile = $DB->get_record('ludimoodle_profile', ['userid' => $userid]);

        if ($profile) {
            $profile->userid = $userid;
            $profile->type = $suggestion;
            $profile->combinedaffinities = $combinedAffinitiesJson;
            $DB->update_record('ludimoodle_profile', $profile);
        } else {
            $profile = new stdClass();
            $profile->userid = $userid;
            $profile->type = $suggestion;
            $profile->combinedaffinities = $combinedAffinitiesJson;
            $DB->insert_record('ludimoodle_profile', $profile);
        }

        foreach ($gameelements as $gameelement) {
            $manager->attribution_game_element($gameelement->id, $userid);
        }

        // Return the suggestion and the combined scores sorted as an array
        return array($suggestion, $combinedScores);
    }

    /**
     * Calculate the matrix suggestion based on user profiles and affinity matrix.
     *
     * @param hexad_scores $hexadScores The user's HEXAD scores.
     * @param array $affinityMatrix The affinity matrix containing the static rules for game elements and profile values.
     * @return array The calculated matrix suggestion for each game element.
     */
    public static function matrixSuggestion(hexad_scores $hexadScores, array $affinityMatrix): array {

        // Initialize an empty array to store the output values
        $output = [];
        // Use the variables defined in static_values.php
        $gameElementsList = static_values::gameElementsList();
        $hexadProfile = static_values::hexadProfile();
        foreach ($gameElementsList as $gameElement) {
            // Initialize the output value for the current game element as 0
            $output[$gameElement] = 0;

            // Calculate the suggestion based on the HEXAD profile
            foreach ($hexadProfile as $profileValue) {
                // Get the user's profile score for the current profile value
                $userProfileScore = self::getProfileValue($profileValue, $hexadScores);

                // Check if the key exists in the affinity matrix
                if (isset($affinityMatrix[$gameElement][$profileValue])) {
                    // Get the static rule from the affinity matrix
                    $staticRule = round($affinityMatrix[$gameElement][$profileValue], 2);
                    // Update the output value by multiplying the user's profile score with the static rule
                    $output[$gameElement] += round($userProfileScore * $staticRule, 2);
                } else {
                    // Handle the case where the key doesn't exist in the affinity matrix
                    // You can choose to skip, set a default value, or handle it differently
                    // Here, we skip the calculation for this profile value
                    continue;
                }
            }
        }

        // Return the calculated output values
        return $output;
    }

    public static function getProfileValue(string $profileValue, hexad_scores $hexadscore) : float {
        $hexadValues = ["achiever", "player", "socialiser", "freespirit", "disruptor", "philanthropist"];

        $profileScore = 0;
        if (in_array($profileValue, $hexadValues)) {
            $profileScore = $hexadscore->getValue($profileValue);
        }

        return $profileScore;
    }
}


