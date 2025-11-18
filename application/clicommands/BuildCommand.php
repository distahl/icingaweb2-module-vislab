<?php

/* Icinga Reporting | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Vislab\Clicommands;

use Icinga\Application\Modules\Module;

use Icinga\Module\Reporting\Cli\Command;


class BuildCommand extends Command
{
    protected $deployPath="/usr/share/icingaweb2/modules/vislab/public/js/vendor/chartbundle.umd.js";
    public function init()
    {
        parent::init();
        $baseDir = Module::get($this->getModuleName())->getBaseDir();

        $this->deployPath=$baseDir
            .DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."js".DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR.'chartbundle.umd.js';

    }
    public function chartjsAction()
    {

        $tmpDir = $this->createTempDir();
        echo "Created temporary directory: $tmpDir\n";

        echo "Prebuild cleanup finished\n";
        $this->writeBuildFiles($tmpDir);
        $this->initNpm($tmpDir);
        echo "Init finished\n";


        $this->build($tmpDir);
        echo "Init finished\n";

        $this->cleanupAction($tmpDir);
        echo "Postbuild cleanup finished\n";
    }

    public function writeBuildFiles($dir)
    {
        $srcDir = $dir.DIRECTORY_SEPARATOR."src";
        mkdir($srcDir);

        $content = sprintf('{
  "name": "chartjs-umd-bundle",
  "source": "%s/index.js",
  "main": "%s",
  "scripts": {
    "build": "microbundle --format umd --name ChartBundle"
  },
  "type": "module"
}
',$srcDir,$this->deployPath);

        file_put_contents($dir.DIRECTORY_SEPARATOR."package.json",$content);

    $indexjs="import Chart from 'chart.js/auto';
import zoomPlugin from 'chartjs-plugin-zoom';
import 'hammerjs';

// Register zoom plugin
Chart.register(zoomPlugin);

export default Chart;
";
    file_put_contents($srcDir.DIRECTORY_SEPARATOR."index.js",$indexjs);

    }
    public function initNpm($dir){
        $this->execute('npm', ['install', '--save-dev', 'chart.js', 'chartjs-plugin-zoom', 'hammerjs'],$dir);

        $this->execute('npm', ['install', '--save-dev', 'microbundle'],$dir);

    }
    public function build($dir){
        $this->execute('npm', ['run', 'build'],$dir);
    }

    public function cleanupAction($dir){

        $this->execute("rm -rf", [$dir]);

    }
    protected function execute($command, $arguments,$workingdir=null)
    {
        $oldCwd = getcwd();
        if($workingdir != null){
            chdir($workingdir);
        }


        $cmd = escapeshellcmd($command);
        foreach($arguments as $argument){
            $cmd.=" ".escapeshellarg($argument);
        }
        $cmd .=" 2>&1";
        exec($cmd, $output, $return_var);

        foreach ($output as $line) {
            echo ($line) . "\n";
        }
        if($workingdir != null){
            chdir($oldCwd);
        }


    }

    protected function createTempDir($prefix = 'tmp_') {
        $tempBaseDir = sys_get_temp_dir();
        do {
            $tempDir = $tempBaseDir . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
        } while (!mkdir($tempDir, 0700));

        return $tempDir;
    }



}
