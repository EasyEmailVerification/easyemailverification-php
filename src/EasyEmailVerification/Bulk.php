<?php

declare(strict_types=1);

namespace EasyEmailVerification;

/** Bulk list verification ($client->bulk). Not available with the sandbox key. */
final class Bulk
{
    private const FINAL_STATES = ['completed', 'failed', 'deleted', 'not_enough_credits'];

    public function __construct(private Client $client)
    {
    }

    /**
     * Uploads a TXT or CSV list (one address per line, max 16 MB) and starts a job.
     *
     * @param string      $file     a file path (or the file contents with $isContent = true)
     * @param string|null $filename name shown in the dashboard (default: the file name, or list.csv)
     * @return array{status: string, list_id: string, filename: string, uploaded: int, message: string}
     */
    public function upload(string $file, ?string $filename = null, bool $isContent = false): array
    {
        if ($isContent) {
            $content = $file;
        } else {
            if (!is_file($file) || ($content = file_get_contents($file)) === false) {
                throw new EEVException('Cannot read file: ' . $file);
            }
            $filename = $filename ?? basename($file);
        }
        return $this->client->request('POST', '/bulk/upload', [
            'multipart' => ['content' => $content, 'filename' => $filename ?? 'list.csv'],
            'timeout' => 300,
        ]);
    }

    /** Status of one job: status, progress (0-100), totals. */
    public function status(string $listId): array
    {
        return $this->client->request('GET', '/bulk/status/' . rawurlencode($listId));
    }

    /** All jobs of the account. */
    public function list(): array
    {
        return $this->client->request('GET', '/bulk/status');
    }

    /** Results of a completed job, as CSV text. */
    public function download(string $listId): string
    {
        return $this->client->request('GET', '/bulk/download/' . rawurlencode($listId), ['raw' => true, 'timeout' => 300]);
    }

    /** Deletes a job and its results. */
    public function delete(string $listId): array
    {
        return $this->client->request('DELETE', '/bulk/' . rawurlencode($listId));
    }

    /** Polls status() until the job is completed, failed, deleted or not_enough_credits. */
    public function wait(string $listId, float $interval = 5.0, float $timeout = 3600.0): array
    {
        $deadline = microtime(true) + $timeout;
        while (true) {
            $job = $this->status($listId);
            if (in_array($job['status'] ?? null, self::FINAL_STATES, true)) {
                return $job;
            }
            if (microtime(true) + $interval > $deadline) {
                throw new EEVException('Bulk job ' . $listId . ' did not finish in time (status: ' . ($job['status'] ?? '?') . ')');
            }
            usleep((int)($interval * 1e6));
        }
    }
}
