<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ToolController extends Controller
{
    public function viewValidateXml()
    {
        // Check if a custom XSD has been uploaded
        $hasXsd = Storage::disk('local')->exists('xsd/goAML.xsd');
        $xsdName = $hasXsd ? 'goAML.xsd (uploaded)' : $this->findDefaultXsd();

        return view('users.tools.view-validate-xml', compact('hasXsd', 'xsdName'));
    }

    public function validateXml(Request $request)
    {
        $request->validate([
            'xml_file' => 'required|file|mimes:xml|max:10240',
        ]);

        try {
            $file = $request->file('xml_file');
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Store the uploaded XML
            $xmlStoragePath = $file->storeAs('xml-uploads', $fileName, 'local');

            // Get the real filesystem path
            $xmlRealPath = Storage::disk('local')->path($xmlStoragePath);

            // Verify file exists
            if (!file_exists($xmlRealPath)) {
                return redirect()->back()->with([
                    'validation_result' => false,
                    'xml_errors' => ["Uploaded file not found at: {$xmlRealPath}"],
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }

            // Basic XML well-formed check
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $loaded = $dom->load($xmlRealPath);

            if (!$loaded) {
                $errors = [];
                foreach (libxml_get_errors() as $error) {
                    $errors[] = "Line {$error->line}: " . trim($error->message);
                }
                libxml_clear_errors();

                // Cleanup
                Storage::disk('local')->delete($xmlStoragePath);

                return redirect()->back()->with([
                    'validation_result' => false,
                    'xml_errors' => $errors,
                    'file_name' => $file->getClientOriginalName(),
                    'message' => 'XML is not well-formed',
                ]);
            }

            // Find XSD: first check uploaded, then default
            $xsdPath = $this->resolveXsdPath();
            $xsdErrors = [];
            $xsdUsed = null;

            if ($xsdPath && file_exists($xsdPath)) {
                $xsdUsed = basename($xsdPath);
                $valid = $dom->schemaValidate($xsdPath);
                if (!$valid) {
                    foreach (libxml_get_errors() as $error) {
                        $xsdErrors[] = "Line {$error->line}: " . trim($error->message);
                    }
                }
                libxml_clear_errors();
            } else {
                $xsdErrors[] = 'No XSD schema file found. Please upload an XSD file in the XML Validator page.';
            }

            // Cleanup uploaded XML
            Storage::disk('local')->delete($xmlStoragePath);

            return redirect()->back()->with([
                'validation_result' => empty($xsdErrors),
                'xml_errors' => $xsdErrors,
                'file_name' => $file->getClientOriginalName(),
                'xsd_used' => $xsdUsed,
                'message' => empty($xsdErrors)
                    ? "XML is valid against {$xsdUsed}!"
                    : 'XML validation failed',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'validation_result' => false,
                'xml_errors' => [$e->getMessage()],
                'message' => 'Validation error',
            ]);
        }
    }

    /**
     * Upload a custom XSD schema file
     */
    public function uploadXsd(Request $request)
    {
        $request->validate([
            'xsd_file' => 'required|file|max:5120',
        ]);

        $file = $request->file('xsd_file');

        // Validate it's actually an XSD
        $content = file_get_contents($file->getPathname());
        if (stripos($content, '<xs:schema') === false && stripos($content, '<xsd:schema') === false) {
            return redirect()->back()->with('error', 'The uploaded file does not appear to be a valid XSD schema.');
        }

        // Store as the active XSD
        $file->storeAs('xsd', 'goAML.xsd', 'local');

        activity()->log('XSD schema file uploaded: ' . $file->getClientOriginalName());

        return redirect()->back()->with('success', 'XSD schema uploaded successfully. XML validations will now use this schema.');
    }

    /**
     * Remove the uploaded XSD (revert to default)
     */
    public function deleteXsd()
    {
        if (Storage::disk('local')->exists('xsd/goAML.xsd')) {
            Storage::disk('local')->delete('xsd/goAML.xsd');
            activity()->log('Custom XSD schema file removed.');
        }

        return redirect()->back()->with('success', 'Custom XSD removed. System will use the default schema if available.');
    }

    /**
     * Resolve which XSD file to use (uploaded first, then default)
     */
    private function resolveXsdPath(): ?string
    {
        // 1. Check for admin-uploaded XSD
        $uploadedXsd = Storage::disk('local')->path('xsd/goAML.xsd');
        if (file_exists($uploadedXsd)) {
            return $uploadedXsd;
        }

        // 2. Check default locations
        $defaults = [
            Storage::disk('local')->path('goAMLSchema_5.0.2.xsd'),
            storage_path('app/goAMLSchema_5.0.2.xsd'),
            base_path('storage/app/goAMLSchema_5.0.2.xsd'),
        ];

        foreach ($defaults as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Find the name of the default XSD if it exists
     */
    private function findDefaultXsd(): ?string
    {
        if (Storage::disk('local')->exists('goAMLSchema_5.0.2.xsd')) {
            return 'goAMLSchema_5.0.2.xsd (default)';
        }
        return null;
    }
}
