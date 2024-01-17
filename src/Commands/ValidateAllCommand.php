<?php

namespace PHPUnuhi\Commands;

use PHPUnuhi\Components\Validator\CaseStyleValidator;
use PHPUnuhi\Components\Validator\EmptyContentValidator;
use PHPUnuhi\Components\Validator\MissingStructureValidator;
use PHPUnuhi\Components\Validator\RulesValidator;
use PHPUnuhi\Configuration\ConfigurationLoader;
use PHPUnuhi\Exceptions\ConfigurationException;
use PHPUnuhi\Facades\CLI\CoverageCliFacade;
use PHPUnuhi\Facades\CLI\ReporterCliFacade;
use PHPUnuhi\Facades\CLI\TranslationSetCliFacade;
use PHPUnuhi\Facades\CLI\ValidatorCliFacade;
use PHPUnuhi\Services\Loaders\Xml\XmlLoader;
use PHPUnuhi\Traits\CommandTrait;
use PHPUnuhi\Traits\StringTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValidateAllCommand extends Command
{
    use CommandTrait;
    use StringTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName(CommandNames::VALIDATE_ALL)
            ->setDescription('Validates everything')
            ->addOption('configuration', null, InputOption::VALUE_REQUIRED, '', '')
            ->addOption('report-format', null, InputOption::VALUE_REQUIRED, 'The report format for a generated report', '')
            ->addOption('report-output', null, InputOption::VALUE_REQUIRED, 'The report output filename for the generated report', '')
            ->addOption('ignore-coverage', null, InputOption::VALUE_NONE, 'Ignore a configured coverage setting and proceed with strict validation.');

        parent::configure();
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws ConfigurationException
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('PHPUnuhi Validate');
        $this->showHeader();

        $configFile = $this->getConfigFile($input);
        $reportFormat = $this->getConfigStringValue('report-format', $input);
        $reportFilename = $this->getConfigStringValue('report-output', $input);
        $ignoreCoverage = $this->getConfigBoolValue('ignore-coverage', $input);

        # -----------------------------------------------------------------

        $validators = [];
        $validators[] = new MissingStructureValidator();
        $validators[] = new CaseStyleValidator();
        $validators[] = new EmptyContentValidator();
        $validators[] = new RulesValidator();

        $translationSetCLI = new TranslationSetCliFacade($io);
        $coverageCLI = new CoverageCliFacade($io);
        $validatorsCLI = new ValidatorCliFacade($io, $validators);
        $reporterCLI = new ReporterCliFacade($io);

        # -----------------------------------------------------------------
        # -----------------------------------------------------------------

        $configLoader = new ConfigurationLoader(new XmlLoader());
        $config = $configLoader->load($configFile);

        $useCoverageOnly = $config->hasCoverageSetting();

        if ($ignoreCoverage) {
            $useCoverageOnly = false;
            $io->block('Coverage has been ignored. Proceeding with strict validation.');
        }

        $translationSetCLI->showConfig($config->getTranslationSets());

        $validatorsResult = $validatorsCLI->execute($config);

        $coverageResult = $useCoverageOnly ? $coverageCLI->execute($config) : true;

        $reporterCLI->execute($reportFormat, $reportFilename, $validatorsResult);

        # -----------------------------------------------------------------

        $isAllValid = ($validatorsResult->getFailureCount() === 0);

        # if we have a coverage setting
        # then ignore all our results -> in this case these are only warnings
        # and only our (soft) coverage result really counts
        if ($useCoverageOnly) {
            $isAllValid = true;
        }

        if (!$coverageResult) {
            $isAllValid = false;
        }

        if ($useCoverageOnly) {
            $io->block('Coverage has been configured. Please keep in mind that only these tests are considered for the validation result!');
        }

        if ($isAllValid) {
            if ($validatorsResult->getFailureCount() > 0) {
                $io->warning('Validation successful, but found ' . $validatorsResult->getFailureCount() . ' warnings!');
            } else {
                $io->success('All translations are valid!');
            }

            return 0;
        }

        $io->error('Found ' . $validatorsResult->getFailureCount() . ' errors!');
        return 1;
    }
}