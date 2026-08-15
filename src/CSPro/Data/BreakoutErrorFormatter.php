<?php

namespace App\CSPro\Data;

/**
 * Met en forme les erreurs de breakout en messages CLAIRS et LISIBLES, pour
 * deux publics distincts :
 *
 *  - shortMessage()      : message court destiné à l'opérateur (UI / colonne
 *                          error_message en base). Pas de stack trace, juste la
 *                          cause réelle + un contexte utile.
 *  - structuredLogBlock(): bloc formaté pour les logs fichier (var/logs/breakout).
 *                          Message clair en tête, puis la trace technique complète
 *                          en dessous pour le développeur.
 *
 * Objectif : ne plus jamais noyer un « SQLSTATE ... 1064 » sous des centaines de
 * lignes « #0 /var/www/html/vendor/... ». Aucune dépendance ; méthodes statiques.
 */
class BreakoutErrorFormatter {

    /**
     * Message court et lisible pour l'UI / la base.
     * Ex. : "serializeCases: SQLSTATE[42000] 1064 — erreur de syntaxe près de 'key'"
     *
     * @param \Throwable  $e     Exception attrapée par le worker.
     * @param string|null $step  Étape logique (serializeCases, deleteQuestionnaires…),
     *                           extraite si non fournie.
     */
    public static function shortMessage(\Throwable $e, ?string $step = null): string {
        $sqlError = self::extractSqlError($e);
        $step = $step ?? self::guessStep($e);

        $message = $sqlError !== null ? $sqlError : self::firstMeaningfulMessage($e);
        $message = self::truncate($message, 800);

        return $step !== null ? ($step . ': ' . $message) : $message;
    }

    /**
     * Bloc structuré pour le log fichier : entête claire + trace complète.
     */
    public static function structuredLogBlock(\Throwable $e, array $context = []): string {
        $lines = [];
        $lines[] = '❌ BREAKOUT FAILED';
        foreach ($context as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $lines[] = sprintf('  %-11s: %s', $label, $value);
        }
        $lines[] = sprintf('  %-11s: %s', 'Error', self::shortMessage($e, $context['Step'] ?? null));
        $lines[] = '  ' . str_repeat('─', 46);
        $lines[] = '  Technical trace (for developers):';
        $lines[] = (string) $e;
        return implode("\n", $lines);
    }

    /**
     * Extrait le message SQL réel (SQLSTATE + fragment pertinent) en remontant la
     * chaîne des exceptions précédentes, sans la stack trace.
     */
    private static function extractSqlError(\Throwable $e): ?string {
        $current = $e;
        while ($current !== null) {
            $msg = $current->getMessage();
            if (stripos($msg, 'SQLSTATE') !== false) {
                // Ne garder que la 1re ligne (le message), pas l'éventuelle trace
                // que certains drivers collent dans le message.
                $firstLine = trim(strtok($msg, "\n"));
                return $firstLine !== false ? $firstLine : $msg;
            }
            $current = $current->getPrevious();
        }
        return null;
    }

    /**
     * À défaut de SQLSTATE, prend le message le plus « profond » (cause racine)
     * mais nettoyé de toute trace.
     */
    private static function firstMeaningfulMessage(\Throwable $e): string {
        $deepest = $e;
        while ($deepest->getPrevious() !== null) {
            $deepest = $deepest->getPrevious();
        }
        return trim(strtok($deepest->getMessage(), "\n")) ?: $deepest->getMessage();
    }

    /**
     * Devine l'étape du breakout à partir de la méthode du serializer présente
     * dans la trace (serializeCases, deleteQuestionnaires, serializeNotes…).
     */
    private static function guessStep(\Throwable $e): ?string {
        $known = [
            'serializeCases', 'deleteQuestionnaires', 'serializeQuestionnaireLevel',
            'serializeQuestionnaireRecords', 'serializeNotes', 'getCaseIdsMap',
            'getQuestionnarieListToSerilaize', 'createJob', 'processNextJob',
        ];
        $current = $e;
        while ($current !== null) {
            foreach ($current->getTrace() as $frame) {
                if (isset($frame['function']) && in_array($frame['function'], $known, true)) {
                    return $frame['function'];
                }
            }
            $current = $current->getPrevious();
        }
        return null;
    }

    private static function truncate(string $s, int $max): string {
        $s = trim($s);
        return strlen($s) > $max ? substr($s, 0, $max - 1) . '…' : $s;
    }
}
