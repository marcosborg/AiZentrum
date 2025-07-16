<?php

namespace App\Services;

use CloudConvert\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Illuminate\Support\Facades\Storage;

class CloudConvertService
{
    protected $cloudConvert;

    public function __construct()
    {
        $this->cloudConvert = new CloudConvert([
            'api_key' => env('CLOUDCONVERT_API_KEY'),
            'sandbox' => false,
        ]);
    }

    public function convertPdfToJpg(string $pdfPath, string $outputFileName): ?string
    {
        $filePath = storage_path("app/{$pdfPath}");

        $job = $this->cloudConvert->jobs()->create(
            (new Job())
                ->addTask(
                    (new Task('import/upload'))->set('name', 'import-my-file')
                )
                ->addTask(
                    (new Task('convert'))
                        ->set('name', 'convert-my-file')
                        ->set('input', 'import-my-file')
                        ->set('input_format', 'pdf')
                        ->set('output_format', 'jpg')
                        ->set('engine', 'poppler')
                        ->set('pages', '1') // só a primeira página
                        ->set('output', [
                            'filename' => $outputFileName
                        ])
                )
                ->addTask(
                    (new Task('export/url'))
                        ->set('input', 'convert-my-file')
                        ->set('archive_multiple_files', false)
                )
        );

        $uploadTask = $job->getTasks()->whereName('import-my-file')->first();
        $this->cloudConvert->tasks()->upload($uploadTask, fopen($filePath, 'r'));

        // Espera o job terminar
        $job = $this->cloudConvert->jobs()->wait($job->getId());

        $exportTask = $job->getTasks()->whereName('export/url')->first();
        $file = $exportTask->getResult()['files'][0] ?? null;

        if ($file && isset($file['url'])) {
            return $file['url'];
        }

        return null;
    }
}
