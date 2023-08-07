<?php

namespace App\Console\Commands;

use App\Models\PdfFolderPath;
use Illuminate\Console\Command;
use HeadlessChromium\BrowserFactory;
use GuzzleHttp\Client;

class ProcessPDFs extends Command
{
    protected $signature = 'pdfs:process';
    protected $description = 'Download and process PDFs from WorkSafe website.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        error_reporting(error_reporting() & ~E_WARNING);

        require 'vendor/autoload.php';

        // Define the base URL
        $baseUrl = 'https://www.worksafe.govt.nz/publications-and-resources/FilterSearchForm?Search=&Topics=Air+quality&Industries=&PublicationTypes=ACOP&action_resultsWithFilter=GoWithFilter';

        // Create a variable to keep track of the start parameter in the URL
        $start = 0;

        // Create an instance of the browser factory
        $browserFactory = new BrowserFactory();

        // Launch the browser
        $browser = $browserFactory->createBrowser();

        // Create a new page
        $page = $browser->createPage();

        // Initialize Guzzle client
        $client = new Client();

        // Function to extract topic and industry from the URL
        function extractParamsFromUrl($url)
        {
            $params = [];
            if (preg_match('/Topics=([^&]+)/', $url, $matches)) {
                $params['topic'] = $matches[1];
            }
            if (preg_match('/Industries=([^&]+)/', $url, $matches)) {
                $params['industry'] = $matches[1];
            }
            return $params;
        }

        // Set the destination base directory
        $baseDirectory = storage_path('app/public/PDF_Folder'); // Base directory to save the files

        // Loop to load more content until there are no more results
        do {
            // Create the URL with the start parameter
            $url = $baseUrl . '&start=' . $start;

            // Navigate to the URL
            $page->navigate($url)->waitForNavigation();

            // Get the current topic and industry parameters from the URL
            $params = extractParamsFromUrl($url);
            $topic = $params['topic'] ?? 'Unknown Topic';
            $industry = $params['industry'] ?? null;

            // Create the destination directory based on topic and industry
            if ($industry) {
                $directory = $baseDirectory . '/' . $topic . '/' . $industry;
            } else {
                $directory = $baseDirectory . '/' . $topic;
            }

            // Create the directory if it doesn't exist
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            // Evaluate JavaScript code to extract the document links
            $documentLinks = $page->evaluate("
                Array.from(document.querySelectorAll('.content-holder a')).map(link => link.href);
            ")->getReturnValue();

            // Loop through the document links and download/update the PDFs
            foreach ($documentLinks as $documentLink) {
                // Send a GET request to the PDF URL
                $response = $client->get($documentLink);

                // Get the contents of the response
                $pdfContent = $response->getBody();

                // Get the original filename from the Content-Disposition header
                $originalFilename = '';
                $contentDisposition = $response->getHeaderLine('Content-Disposition');
                if (preg_match('/filename="([^"]+)"/', $contentDisposition, $matches)) {
                    $originalFilename = $matches[1];
                }

                // Set the destination file path
                $filename = $originalFilename; // Filename or fallback to 'Untitled.pdf'
                $destination = $directory . '/' . $filename;

                if (file_exists($destination)) {
                    if (is_file($destination)) {
                        // Delete the existing file
                        unlink($destination);
                        echo 'Deleted existing file: ' . $filename . PHP_EOL . '<br>';
                    }
                }

                // Save the PDF file
                $result = file_put_contents($destination, $pdfContent);
                if ($result !== false) {
                    PdfFolderPath::updateOrCreate(['name' => $filename],['name' => $filename, 'path'=>$destination]);
                    echo 'Downloaded: ' . $filename . PHP_EOL . '/n';
                }
            }

            // Increment the start parameter for the next iteration
            $start += 10;

            // Check if there are more results by evaluating the presence of the "Show More" button
            $hasMoreResults = $page->evaluate("
        !!document.querySelector('.js-show_more_ajax')
        ")->getReturnValue();
            } while ($hasMoreResults);

        // Close the browser
        $browser->close();
        // The original script code ends here
        $this->info('PDFs processed successfully.');

    }
}
