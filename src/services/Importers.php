<?php

namespace barrelstrength\sproutbaseimport\services;

use barrelstrength\sproutbase\app\import\base\Importer;
use barrelstrength\sproutbase\SproutBaseImport;
use barrelstrength\sproutbase\app\import\importers\elements\Asset;
use barrelstrength\sproutbase\app\import\importers\elements\Category;
use barrelstrength\sproutbase\app\import\importers\elements\Entry;
use barrelstrength\sproutbase\app\import\importers\elements\Tag;
use barrelstrength\sproutbase\app\import\importers\elements\User;
use barrelstrength\sproutbase\app\import\importers\fields\Assets;
use barrelstrength\sproutbase\app\import\importers\fields\Categories;
use barrelstrength\sproutbase\app\import\importers\fields\Checkboxes;
use barrelstrength\sproutbase\app\import\importers\fields\Color;
use barrelstrength\sproutbase\app\import\importers\fields\Date;
use barrelstrength\sproutbase\app\import\importers\fields\Dropdown;
use barrelstrength\sproutbase\app\import\importers\fields\Entries;
use barrelstrength\sproutbase\app\import\importers\fields\Lightswitch;
use barrelstrength\sproutbase\app\import\importers\fields\Matrix;
use barrelstrength\sproutbase\app\import\importers\fields\MultiSelect;
use barrelstrength\sproutbase\app\import\importers\fields\Number;
use barrelstrength\sproutbase\app\import\importers\fields\Email;
use barrelstrength\sproutbase\app\import\importers\fields\PlainText;
use barrelstrength\sproutbase\app\import\importers\fields\Products;
use barrelstrength\sproutbase\app\import\importers\fields\RadioButtons;
use barrelstrength\sproutbase\app\import\importers\fields\Redactor;
use barrelstrength\sproutbase\app\import\importers\fields\Table;
use barrelstrength\sproutbase\app\import\importers\fields\Tags;
use barrelstrength\sproutbase\app\import\importers\fields\Url;
use barrelstrength\sproutbase\app\import\importers\fields\Users;
use barrelstrength\sproutbase\app\import\importers\settings\Field;
use barrelstrength\sproutbase\app\import\importers\settings\Section;
use barrelstrength\sproutbase\app\import\importers\settings\Widget;
use barrelstrength\sproutbase\app\import\base\FieldImporter as BaseFieldImporter;
use barrelstrength\sproutbase\app\import\models\Seed as SeedModel;
use barrelstrength\sproutbase\app\import\models\Weed;
use craft\base\Component;
use craft\base\Element;
use craft\events\RegisterComponentTypesEvent;
use Craft;

/**
 *
 * @property array $sproutImportSeedImporters
 * @property array $sproutImportFieldImporters
 * @property array $sproutImportImporters
 */
class Importers extends Component
{
    const EVENT_REGISTER_IMPORTER_TYPES = 'registerImporterTypes';

    /**
     * @var array
     */
    protected $importers = [];

    /**
     * @var array
     */
    protected $fieldImporters = [];

    /**
     * @var array
     */
    protected $seedImporters = [];

    /**
     * @return array
     */
    public function getSproutImportImporters(): array
    {
        $importerTypes = [

            // Elements
            Asset::class,
            Category::class,
            Entry::class,
            Tag::class,
            User::class,

            // Fields
            Assets::class,
            Categories::class,
            Checkboxes::class,
            Color::class,
            Date::class,
            Dropdown::class,
            Email::class,
            Entries::class,
            Lightswitch::class,
            Matrix::class,
            MultiSelect::class,
            Number::class,
            PlainText::class,
            RadioButtons::class,
            Table::class,
            Tags::class,
            Url::class,
            Users::class,

            // Settings
            Field::class,
            Section::class,
            Widget::class
        ];

        if (Craft::$app->getPlugins()->getPlugin('redactor')) {
            $importerTypes[] = Redactor::class;
        }

        if (Craft::$app->getPlugins()->getPlugin('commerce')) {
            $importerTypes[] = Products::class;
        }

        $event = new RegisterComponentTypesEvent([
            'types' => $importerTypes
        ]);

        $this->trigger(self::EVENT_REGISTER_IMPORTER_TYPES, $event);

        $importers = $event->types;

        if (!empty($importers)) {
            foreach ($importers as $importerNamespace) {

                // Create an instance of our Importer object
                $importer = new $importerNamespace;

                // Pluck any Field Importers for their own list
                if ($importer && $importer instanceof BaseFieldImporter) {
                    $this->fieldImporters[$importerNamespace] = $importer;
                    continue;
                }

                if ($importer && $importer instanceof Importer) {
                    $this->importers[$importerNamespace] = $importer;

                    if ($importer->hasSeedGenerator()) {
                        $this->seedImporters[$importerNamespace] = $importer;
                    }
                }
            }
        }

        uasort($this->importers, function($a, $b) {
            /**
             * @var $a Importer
             * @var $b Importer
             */
            return $a->getName() <=> $b->getName();
        });

        uasort($this->fieldImporters, function($a, $b) {
            /**
             * @var $a Importer
             * @var $b Importer
             */
            return $a->getName() <=> $b->getName();
        });

        uasort($this->seedImporters, function($a, $b) {
            /**
             * @var $a Importer
             * @var $b Importer
             */
            return $a->getName() <=> $b->getName();
        });

        return $this->importers;
    }

    /**
     * @return array
     */
    public function getSproutImportSeedImporters()
    {
        if (count($this->seedImporters)) {
            return $this->seedImporters;
        }

        $this->getSproutImportImporters();

        return $this->seedImporters;
    }

    /**
     * @return array
     */
    public function getSproutImportFieldImporters()
    {
        // Make sure all of our Field Type classes are loaded

        if (count($this->fieldImporters)) {
            return $this->fieldImporters;
        }

        $this->getSproutImportImporters();

        return $this->fieldImporters;
    }

    /**
     * @param $namespace
     *
     * @return null
     */
    public function getFieldImporterClassByType($namespace)
    {
        $this->getSproutImportFieldImporters();

        $fieldClass = null;

        if ($this->fieldImporters !== null) {
            foreach ($this->fieldImporters as $importer) {
                if ($importer->getModelName() == $namespace) {
                    $fieldClass = $importer;
                }
            }
        }

        return $fieldClass;
    }

    /**
     * Get the Importer Model based on the "@model" key in the import data row
     * and return it if it exists.
     *
     * Examples:
     * - "@model": "barrelstrength\\sproutbase\\app\\import\\importers\\elements\\Entry"
     * - "@model": "barrelstrength\\sproutbase\\app\\import\\importers\\settings\\Field"
     *
     * @param      $settings
     *
     * @return bool | Importer
     */
    public function getImporter($settings)
    {
        // Make sure we have an @model attribute
        if (!isset($settings['@model'])) {
            $message = Craft::t('sprout-base', 'Importer class not found. Each item being imported requires an "@model" attribute.');

            $errorLog = [];
            $errorLog['message'] = $message;
            $errorLog['attributes'] = $settings;

            SproutBaseImport::error($message);

            $this->addError('invalid-model-key', $errorLog);

            return false;
        }

        // Make sure the @model attribute class exists
        if (!class_exists($settings['@model'])) {

            $message = Craft::t('sprout-base', 'Class defined in @model attribute could not be found: {class}', [
                'class' => $settings['@model']
            ]);

            SproutBaseImport::error($message);

            $this->addError('invalid-namespace', $message);

            return false;
        }

        $importerClass = $settings['@model'];

        // Remove our @model so only the actual settings exist in the $settings array
        unset($settings['@model']);

        // Instantiate our Importer's Class
        return new $importerClass($settings);
    }

    /**
     * @param           $importData
     * @param Weed|null $weedModel
     *
     * @return bool|\craft\base\Model|mixed|null
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function save($importData, Weed $weedModel = null)
    {
        $newModel = false;
        $importer = null;

        if ($importData === null) {
            return false;
        }

        // @todo - document which situations each condition below applies
        if (is_array($importData)) {
            // When seeding entries, we have an array
            $rows = $importData;
        } else {
            $rows = json_decode($importData, true);
        }

        foreach ($rows as $row) {
            /**
             * @var $importerClass Importer
             */
            $importerClass = $this->getImporter($row);

            // Confirm model for this row of import data is supported
            if (!$importerClass) continue;

            if ($importerClass->model instanceof Element) {
                /**
                 * @var $importerClass \barrelstrength\sproutbase\app\import\base\ElementImporter
                 */
                $newModel = SproutBaseImport::$app->elementImporter->saveElement($row, $importerClass);
            } else {
                $newModel = SproutBaseImport::$app->settingsImporter->saveSetting($row, $importerClass);
            }

            if (!$this->isWeedable($weedModel, $newModel)) {
                continue;
            }

            $seedAttributes = [
                'itemId' => $newModel->id,
                'type' => get_class($importerClass),
                'seedType' => $weedModel->seedType,
                'details' => $weedModel->details,
                'dateCreated' => $weedModel->dateSubmitted
            ];

            $seedModel = new SeedModel();

            $seedModel->setAttributes($seedAttributes, false);
            if (!$importerClass->isUpdated) {
                SproutBaseImport::$app->seed->trackSeed($seedModel);
            }
        }

        // Assign importer errors to utilities error for easy debugging and call of errors.
        if ($this->hasErrors()) {
            SproutBaseImport::$app->importUtilities->addErrors($this->getErrors());
        }

        return $newModel;
    }

    protected function isWeedable(Weed $weedModel = null, $newModel)
    {
        if ($weedModel === null) {
            return false;
        }

        if ($weedModel->seed !== true) {
            return false;
        }

        if (!isset($newModel->id)) {
            return false;
        }

        return true;
    }
}
