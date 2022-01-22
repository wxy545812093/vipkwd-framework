<?php
class FFmpeg{

    private $opts;

    public function __construct($options = array()){
        $this->opts = array_merge([
            'command' => '/usr/local/ffmpeg/bin/ffmpeg',
            'output_dir' => ''
        ], $options);

        $this->parsePathSeparator();

        //TODO others
    }

    // 获取媒体信息
    public function getInfo($filepath, $raw = false){

        if( true !== ($result =  $this->validateFile($filepath)) ){
            return $result;
        }

        $shell = vsprintf('%s -i "%s" 2>&1', [
            $this->opts['command'],
            $filepath
        ]);
        $raws = $this->exec($shell);

        // ob_start();
        // passthru(sprintf($this->opts['command'].' -i "%s" 2>&1', $filepath));
        // $raws = ob_get_contents();
        // ob_end_clean();

        // 通过使用输出缓冲，获取到ffmpeg所有输出的内容。
        $ret = ['basic'=>[], 'video'=>'', 'audio'=>''];
        $ret['basic'] = self::fileBaseInfo($filepath);

        // Duration: 01:24:12.73, start: 0.000000, bitrate: 456 kb/s
        if (preg_match("/Duration: (.*?), start: (.*?), bitrate: (\d*) kb\/s/", $raws, $match)) {
            $da = explode(':', $match[1]);
            $ret['basic']['duration'] = $match[1]; // 提取出播放时间
            $ret['basic']['seconds'] = $da[0] * 3600 + $da[1] * 60 + $da[2]; // 转换为秒
            $ret['basic']['start'] = $match[2]; // 开始时间
            $ret['basic']['bitrate'] = $match[3]; // bitrate 码率 单位 kb
        }

        // Stream #0.1: Video: rv40, yuv420p, 512x384, 355 kb/s, 12.05 fps, 12 tbr, 1k tbn, 12 tbc
        // Stream #0:1(und): Video: h264 (High) (avc1 / 0x31637661), yuv420p(tv, smpte170m/bt470bg/smpte170m), 448x960, 1209 kb/s, 30.18 fps, 30.18 tbr, 180k tbn, 360k tbc (default)
        if (preg_match("/Video: (.*?), (.*?), (\d+x\d+),(.*?)\ +([0-9\.]+)\ +fps/is", $raws, $match)) {
            preg_match("/Video: (.*)rotate\ +:\ +(\d+)/is", $raws, $rotate);
            preg_match("/Video: (.*)creation_time\ +:\ +([0-9-A-Za-z\:\.]+)/is", $raws, $creation_time);
            $a = explode('x', $match[3]);
            $ret['video'] = [
                'vcodec'  => $match[1], // 编码格式
                'vformat' => $match[2], // 视频格式
                'resolution' => $match[3], // 分辨率
                'width'  => $a[0],
                'height' => $a[1],
                'fps' => $match[5],
                'rotate' => isset($rotate[2]) ? $rotate[2] : '',
                'creation_time' => isset($creation_time[2]) ? $creation_time[2] : '',
            ];
            //dump($ret['video'], $match, $creation_time);
        }
        // Stream #0.0: Audio: cook, 44100 Hz, stereo, s16, 96 kb/s
        if (preg_match("/Audio: ([0-9a-zA-Z\(\)\ \/\.\-]+), (\d*) Hz/is", $raws, $match)) {
            preg_match("/Audio: (.*)creation_time\ +:\ +([0-9-A-Za-z\:\.]+)/is", $raws, $creation_time);
            $ret['audio'] = [
                'acodec' => $match[1],       // 音频编码
                'asamplerate' => $match[2],  // 音频采样频率
                'creation_time' => isset($creation_time[2]) ? $creation_time[2] : '',
            ];
        }

        if (isset($ret['basic']['seconds']) && isset($ret['basic']['start'])) {
            $ret['basic']['play_time'] = $ret['basic']['seconds'] + $ret['basic']['start']; // 实际播放时间
        }

        return $this->_reasponse($ret, $raws, $shell,  $raw);
    }

    private function _reasponse($ret, $raws, $shell = false, $raw = false){
        if($shell !== false){
            $ret['shell'] = $shell;
        }
        if($raw){
            $ret['raws'] = "\r\n";
            $ret['raws'] .= str_pad("\r\n", 150 , '-', STR_PAD_LEFT);
            $ret['raws'] .=$raws;
            $ret['raws'] .=str_pad("\r\n", 150 , '-');
        }

        return $ret;
    }

    // 角度旋转
    // $direction r/l
    // $degrees 0 90 18 270 360
    /*$info = $ffmpeg->rotate([
        'file' => $file,
        'degrees' => 180,
        'direction' => 'r',
        'output_dir' => '',
        'ext' => 'mp4'
    ]);
    */
    public function rotate($options, $bash = false){
        /*
        #顺时针旋转画面90度
        ffmpeg -i test.mp4 -vf "transpose=1" out.mp4 

        #逆时针旋转画面90度
        ffmpeg -i test.mp4 -vf "transpose=2" out.mp4 

        #顺时针旋转画面90度再水平翻转
        ffmpeg -i test.mp4 -vf "transpose=3" out.mp4 

        #逆时针旋转画面90度水平翻转
        ffmpeg -i test.mp4 -vf "transpose=0" out.mp4 

        #水平翻转视频画面
        ffmpeg -i test.mp4 -vf hflip out.mp4 

        #垂直翻转视频画面
        ffmpeg -i test.mp4 -vf vflip out.mp4
        */
        if( true !== ($result =  $this->validateFile($options['file'])) ){
            return $result;
        }

        if( ($mod = $options['degrees'] % 90 ) > 0){
            $options['degrees'] -= $mod;
        }
        if($options['direction'] == 'r'){
            $directionkey = 1;
        }else{
            $directionkey = 2;
        }

        $text = "transpose={$directionkey}";
        for($count = ($options['degrees'] / 90); $count>1; $count--){
            $text .=",transpose={$directionkey}";
        }
        
        $output = $this->output($options);

        $shell = vsprintf('%s -y -i "%s" -vf "%s" %s 2>&1', [
            $this->opts['command'],
            $options['file'],
            $text,
            $output
        ]);
        if($bash === true){
            return $shell;
        }
        $raws = $this->exec($shell);

        if(is_file($output)){
            if(isset($options['replace']) && $options['replace'] === true){
                $file = dirname($output) .'/'. basename($options['file']);
                rename($output, $file);
            }
            $ret = [
                'ofile' => $options['file'],
                'nfile' => $file ?? $output
            ];
        }else{
            $ret = [
                'ofile' => $options['file'],
                'msg'=>'处理错误',
            ];
        }
        return $this->_reasponse($ret, $raws, $shell);
    }


    /*
    $info= $ffmpeg->cut([
        'file' => $file,
        'ext' => 'mp4',
        'output_dir' => '',
        'start' => 0,  //1 可以 以秒为单位计数： 80；2 可以是 H:i:s 格式 00:01:20
        'end' => 540  //1 可以 以秒为单位计数： 80；2 可以是 H:i:s 格式 00:01:20
    ]);
    */
    public function cut($options){

        $output = $this->output($options);

        $shell = vsprintf('%s -y -ss %s -t %s -accurate_seek -i "%s" -vcodec copy -acodec copy -avoid_negative_ts 1 %s 2>&1', [
            $this->opts['command'],
            $options['start'],
            $options['end'],
            $options['file'],
            $output
        ]);
        $raws = $this->exec($shell);

        $ret['ofile'] = $options['file'];
        if(!is_file($output)){
            $ret['msg'] = '处理错误';
        }else{
            $ret['output'] = $output;
        }
        return $this->_reasponse($ret, $raws, $shell);
    }

    public function mergeImagesToVideo(){

        // $shell = vsprintf('%s -i "%s" 2>&1', [
        //     $this->opts['command'],
        //     $filepath
        // ]);
        // $raws = $this->exec($shell);

        ob_start();
        passthru(sprintf($this->opts['command'] .' -y -r 1 -i "%s" -vf "'.$text.'" '.$output.' 2>&1', $options['file']));
        $raw = ob_get_contents();
        ob_end_clean();

        //./bin/ffmpeg.exe -y -r 1 -i 'images/%d.png' -vcodec huffyuv images.avi
    }

    public function concatWithSamePolicy($options){
        
        $output = $this->output($options);
        
        $shell = vsprintf('%s -y -f concat -safe 0 -i "%s" -c copy %s 2>&1', [
            $this->opts['command'],
            $options['file'],
            $output
        ]);

        $raws = $this->exec($shell);

        $ret['ofile'] = $options['file'];
        if(!is_file($output)){
            $ret['msg'] = '处理错误';
        }else{
            $ret['output'] = $output;
        }
        return $this->_reasponse($ret, $raws, $shell);
    }

    /*
    $info = $ffmpeg->textWater([
        'file' => $file,
        'fontsize' => 20,
        'fontcolor' => '00ff00',// #00ff00
        'box' => 1, //是否开启文字边框 1是 0否
        'boxcolor' => 'black',//边框背景
        'alpha' => '0.4',
        'text' => 'CAM: {@time2020-12-12 12:13:14}',
        'output_dir' => '',
        'save_name' => '',
        'position' => '0',
        'axio' => '50,50',
        'ttf' => 'ttfs/1.ttf',
        'ext' => 'mp4'
    ]);
    dump($info);
    */
    public function textWater($options, $raw = false){
        // $path = appPath(__FILE__) . '/1.mp4';

        switch ($options['position']) {
            case '0':
                $axio = explode(',', $options['axio']);
                $x = '(w-tw)-'.abs($axio[0]??1);
                $y = '(h-text_h)-'.abs($axio[1]??1);
                break;
            case '1':
                $x = '1';
                $y = '1';
                break;
            case '2':
                $x = '(w-tw)/2';
                $y = '1';
                break;
            case '3':
                $x = '(w-tw)-1';
                $y = '1';
                break;
            case '4':
                $x = '1';
                $y = '(h-text_h)/2';
                break;
            case '5':
                $x = '(w-tw)/2';
                $y = '(h-text_h)/2';
                break;
            case '6':
                $x = '(w-tw)-1';
                $y = '(h-text_h)/2';
                break;
            case '7':
                $x = '1';
                $y = '(h-text_h)';
                break;
            case '8':
                $x = '(w-tw)/2';
                $y = '(h-text_h)';
                break;
            case '9':
            default:
                $x = '(w-tw)-1';
                $y = '(h-text_h)';
                break;
        }

        $drawtext = 'drawtext=fontfile='.$options['ttf'];
        $drawtext .=": x=$x:y=$y";
        if($options['fontsize']){
            $drawtext .=": fontsize={$options['fontsize']}";
        }
        if($options['fontcolor']){
            $drawtext .=": fontcolor={$options['fontcolor']}";
        }
        if($options['box']){
            $drawtext .=": box={$options['box']}";
            $drawtext .=": boxborderw=7";
            $drawtext .=": boxcolor={$options['boxcolor']}@".(floatval($options['alpha'])??1);
        }
        if($options['text']){
            if(preg_match("/\{@time(.*)\}/", $options['text'], $result)){
                $options['text'] = str_replace($result[0], '%{pts:gmtime:'.(strtotime($result[1])+3600 *8).'}' , $options['text']);
                $options['text'] = str_replace(':', '\\:', $options['text']);
            }
            $drawtext .=": text='{$options['text']}'"; 
        }

        // echo $drawtext;
        // dump($options,1);

        $meta = self::getInfo($options['file']);
        $output = $this->output($options, $meta['basic']);
        $shell = vsprintf('%s -y -r %s -i "%s" -vf "%s" %s 2>&1', [
            $this->opts['command'],
            ($meta['video']['fps']??24),
            $options['file'],
            $drawtext,
            $output
        ]);
        $raws = $this->exec($shell);

        // ob_start();
        // passthru(sprintf('%s -y -r %s -i "%s" -vf "%s" %s 2>&1', [
        //     $this->opts['command'],
        //     ($meta['video']['fps']??24),
        //     $options['file'],
        //     $drawtext,
        //     $output
        // ]));
        // $raw = ob_get_contents();
        // ob_end_clean();

        $ret['ofile'] = $options['file'];
        if(!is_file($output)){
            $ret['msg'] = '处理错误';
        }else{
            $ret['output'] = $output;
        }
        return $this->_reasponse($ret, $raws, $shell, $raw);
        // ./bin/ffmpeg.exe -y -r 30 -i 111.mp4 -vf "drawtext=fontfile=ttfs/1.ttf: x=w-tw-10:y=10: fontsize=36:fontcolor=yellow: box=1:boxcolor=black@0.4: text='Wall Clock Time\: %{pts\:gmtime\:1456007118}'" 111.mp4
    }

    /*
    $info = $ffmpeg->scaleReset([
        'file' => $file,
        'scale' => '320x240',
        'output_dir' => '',
    ]);
    dump($info);
    */
    public function scaleReset(array $options):array{

        $output = $this->output($options);

        $shell = vsprintf('%s -y -i %s -s %s %s 2>&1', [
            $this->opts['command'],
            $options['file'],
            $options['scale'],
            $output
        ]);

        $raws = $this->exec($shell);

        $ret['ofile'] = $options['file'];
        if(!is_file($output)){
            $ret['msg'] = '处理错误';
        }else{
            $ret['output'] = $output;
        }
        return $this->_reasponse($ret, $raws, $shell);
    }

    /*
    $info = $ffmpeg->scaleReset([
        'file' => $file,
        'scale' => '320x240',
        'axis' => '0,0',
        'output_dir' => '',
    ]);
    dump($info);
    */
    //https://blog.csdn.net/caohang103215/article/details/72638751?utm_medium=distribute.pc_relevant.none-task-blog-searchFromBaidu-3.control&depth_1-utm_source=distribute.pc_relevant.none-task-blog-searchFromBaidu-3.control
    //https://www.cnblogs.com/yongfengnice/p/7095846.html
    public function crop(array $options){

        $output = $this->output($options);

        $crop = str_replace(['x',"X",',',"|","_"],':', str_replace(' ','', $options['scale']));
        if(isset($options['axis']) && $options['axis']){
            $crop .= ":" . str_replace(['x',"X",',',"|","_","."],':', str_replace(' ','', $options['axis']));
        }

        $shell = vsprintf("%s -y -i %s -vf crop='%s'%s -acodec copy %s 2>&1", [
            $this->opts['command'],
            $options['file'],
            $crop,
            (isset($options['seconds']) && $options['seconds'] > 0) ? (' -t '.intval($options['seconds'])) : '',
            $output
        ]);
        $raws = $this->exec($shell);

        $ret['ofile'] = $options['file'];
        if(!is_file($output)){
            $ret['msg'] = '处理错误';
        }else{
            $ret['output'] = $output;
        }
        return $this->_reasponse($ret, $raws, $shell);
    }

    private function output($options, $baseInfo = null){
        $baseInfo = $baseInfo ?? self::fileBaseInfo($options['file']);

        if(!$options['output_dir'] || !is_dir($options['output_dir'])){
            $options['output_dir'] = $baseInfo['dir'];
        }

        if(!$options['output_dir'] && is_dir($this->opt['output_dir'])){    
            $options['output_dir'] = $this->opt['output_dir'];
        }
        $options['output_dir'] = $options['output_dir'] ?? '';
        if(isset($options['save_name']) && $options['save_name']){
            return $output = $options['output_dir'].'/'.$options['save_name'].'.'.($options['ext'] ?? $baseInfo['ext']);
        }
        // $new_file_name = md5( $baseInfo['filename'] . $text . time() ).'.'.($options['ext'] ?? $baseInfo['ext']);
        $new_file_name = hash_file('md5', $options['file']).'.'.($options['ext'] ?? $baseInfo['ext']);
        return $output = $options['output_dir'].'/'.$new_file_name;
    }
    private function exec($shell){
        ob_start();
        passthru($shell);
        $raw = ob_get_contents();
        ob_end_clean();
        return $raw;    
    }
    private function validateFile(&$file){
        $file = str_replace('\\','/', $file);
        if(!is_file($file)){
            return ['error' => "\"$file\" 媒体文件无效"];
        }
        return true;
    }

    private static function fileBaseInfo($filepath){
        $ret = [];
        $ret['path'] = $filepath;
        $ret['dir'] = dirname($filepath);
        $ret['filename'] = basename($filepath);
        $ret['ext'] = pathinfo($filepath)['extension'];
        $ret['size'] = filesize($filepath); // 文件大小
        return $ret;
    }

    private function parsePathSeparator(){
        $this->opts['command'] = preg_replace("#(\/+)#", '/',  str_replace('\\','/', trim($this->opts['command'])) );
        if(!preg_match("/^([a-z]+:|)\/(.*)/i",$this->opts['command']) ){
            exit("[".$this->opts['command']."]ffmpeg 命令无效");
        }
    }
}