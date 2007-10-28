<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generates a sound clip saying the contents of a string of characters
 *
 * Joins up multiple wav file sound clips of letters/numbers being spoken,
 * optionally adding distortion and echo. This could be use to compliment an
 * image-based CAPTCHA to enable people who are unable to read the security
 * image hear it read out instead.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Text
 * @package   Text_Spell_Audio
 * @author    Tom Harwood <tom@r0x0rs.com>
 * @copyright 2006-2007 Tom Harwood
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   CVS: $Id$
 */

/**
 * Requires PEAR packages
 */
require_once 'PEAR.php';

/**
 * Generates an audio wav clips from text
 *
 * Joins up multiple wav file sound clips of letters/numbers being spoken,
 * optionally adding distortion and echo. This could be use to compliment an
 * image-based CAPTCHA to enable people who are unable to read the security
 * image hear it read out instead.
 *
 * The following example above creates the audio file saying 'a a 1'.
 * The wav files 'a.wav', 'a.wav', '1.wav' are joined together; an appropriate
 * Content-type is echoed to the browser, and the 'a a 1' wav file is output.
 *
 * <code>
 * $ac = new Text_Spell_Audio();
 * $ac->output('aa1');
 * </code>
 *
 * This class is supplied with a default directory of sound clips of the
 * characters a-z, 0-9, and #@%&_. Uppercase letters are communicated by adding
 * the prefix 'CAPITAL' (stored in CAPITAL.wav) to the lowercase letters
 * (e.g. B is pronounced 'capital b').
 *
 * You can specify a path to your own folder of clips:
 * <pre>
 * /path/to/wavs/a.wav
 * /path/to/wavs/b.wav
 * ...
 * /path/to/wavs/1.wav
 * /path/to/wavs/2.wav
 * ...
 * /path/to/wavs/35.wav ('#', 35 = ASCII #)
 * ...
 * /path/to/wavs/CAPITAL.wav ('CAPITAL' for forming 'capital X' phrase)
 * </pre>
 *
 * <code>
 * $ac = new Text_Spell_Audio(array('sound_dir' => '/path/to/wavs/'));
 * $ac->output('aa1');
 * </code>
 *
 * The recordings must all be standard uncompressed (PCM) wav files and must
 * all be either 8bit or 16bit, have the same sample rate,
 * and have the same number of channels.
 *
 * @category  Text
 * @package   Text_Spell_Audio
 * @author    Tom Harwood <tom@r0x0rs.com>
 * @copyright 2006-2007 Tom Harwood
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   CVS: $Id$
 */
class Text_Spell_Audio
{
    /**
     * Class options
     *
     * @var integer
     * @access private
     * @see __construct()
     */
    var $_options = array(
        'sound_dir'    => '',
        'distort'      => 0,
        'content_type' => 'audio/x-wav',
        'capital'      => 'before',
        'fold_cases'   => true,
    );

    /**
     * Class constructor
     *
     * PHP4-style constructor
     *
     * @see __construct()
     */
    function Text_Spell_Audio($options = array())
    {
        $this->__construct($options);
    }

    /**
     * Class constructor
     *
     * Use the Audio_CAPTCHA default sound clips
     * ({pear_data_dir}/Text/Audio/Spell/clips/) by default.
     * The default sound clips contain all characters that the PEAR module
     * Text_Password generates.
     *
     * @param array $options Options for class. The following indexes are
     *                       accepted:
     * <ul>
     *  <li>sound_dir (string) path to wav sound files.</li>
     *  <li>distort (integer) distortion level (currently 0 or 1
     *                        supported).</li>
     *  <li>content_type (string) value for the Content-type HTTP header.</li>
     *  <li>capital_format "before" or "after": whether 'CAPITAL' should be
     *                     said before or after the letter</li>
     *  <li>fold_cases (boolean) whether to convert letters to lowercase for
     *                           file name lookup</li>
     * </ul>
     *
     * @access public
     */
    function __construct($options = array())
    {
        $this->_options = array_merge($this->_options, $options);

        if (!$this->_options['sound_dir']) {
            require_once 'PEAR/Config.php';
            $config = PEAR_Config::singleton();

            $soundDir  = $config->get('data_dir');
            $soundDir .= DIRECTORY_SEPARATOR.'Text_Spell_Audio/en/';
            $this->_options['sound_dir'] = $soundDir;
        }
    }

    /**
     * Sets options for class
     *
     * @param array $options options list
     *
     * @return void
     * @access public
     * @see __construct()
     */
    function setOptions($options)
    {
        $this->_options = array_merge($this->_options, $options);
    }

    /**
     * Returns list of options
     *
     * @return array options
     * @access public
     * @see __construct()
     */
    function getOptions()
    {
        return $this->_options;
    }

    /**
     * Generates an audio wav file to spell out characters
     *
     * Example:
     * <code>
     * $binWav = $ac->getAudio('ab1A');
     * </code>
     *
     * @param string $text character string to spell out
     *
     * @return string Binary string of new wav file or PEAR_Error on failure
     * @access public
     */
    function getAudio($text)
    {
        $text = $this->_stringSplit($text);

        // get an array of unique characters so we don't load the same
        // character sound file from disk and decode it more than once
        $charsUsed  = array_unique($text);
        $wavStructs = array();

        // properties of the wav files and new wav file
        $sampleRate = -1;
        $bits       = -1;
        $channels   = -1;

        // sound data of new wav file
        $data = array();

        // holds "capital" sound clip if necessary to say capital letters
        $capitalStruct = 0;

        // loop through all unique characters and get their wav arrays
        foreach ($charsUsed as $char) {
            if ($this->_isCapital($char) && !is_array($capitalStruct)) {

                $capitalStruct = $this->_getDecodedWav('CAPITAL');

                if (PEAR::isError($capitalStruct)) {
                    return $capitalStruct;
                }
            }

            $wavStructs[$char] = $this->_getDecodedWav($char);
            if (PEAR::isError($wavStructs[$char])) {
                return $wavStructs[$char];
            }

            // check this wav file has the same properties as all the others
            if (($sampleRate != -1
                 && $sampleRate!=$wavStructs[$char]['sampleRate'])
                || ($bits != -1
                    && $bits != $wavStructs[$char]['bits'])
                || ($channels != -1
                    && $channels != $wavStructs[$char]['channels'])) {
                $error = PEAR::raiseError('wav have different properties');
                return $error;

            } else {
                $sampleRate = $wavStructs[$char]['sampleRate'];
                $bits       = $wavStructs[$char]['bits'];
                $channels   = $wavStructs[$char]['channels'];
            }
        }

        // if necessary, check if 'CAPITAL' has the same properties as other
        // files
        if (is_array($capitalStruct)) {
            if ($sampleRate != $capitalStruct['sampleRate']
                || $bits != $capitalStruct['bits']
                || $channels != $capitalStruct['channels']) {

                $error = PEAR::raiseError('wav have different properties');
                return $error;
            }
        }

        // build the new wav file by joining up sound samples from the files
        // in the right order

        $data = array();
        foreach ($text as $char) {
            if ($this->_options['capital'] == 'before'
                && $this->_isCapital($char)) {
                $data = array_merge($data, $capitalStruct['data']);
            }

            $data = array_merge($data, $wavStructs[$char]['data']);

            if ($this->_options['capital'] == 'after'
                && $this->_isCapital($char)) {
                $data = array_merge($data, $capitalStruct['data']);
            }
        }

        // optional distortion: mix the sound file with its self backwards
        if ($this->_options['distort'] > 0) {
            $data = $this->_distort($data, $sampleRate, $channels);
        }

        // add one second of silence, this helps people whose audio player loops
        // to distinguish the start of the sound file.
        // 8-bit files are unsigned and require a silence value of 128 (half way
        // between 0-255, otherwise signed files require 0)
        $silence1s = array_fill(0, $sampleRate * $channels,
                                ($bits == 8) ? 128 : 0);
        $data = array_merge($data, $silence1s);

        // optional distortion: add echo to file
        if ($this->_options['distort'] > 0) {
            $data = $this->_echo($data, $sampleRate, $channels);
        }

        // Start generating the new wav file.
        // Magic constants and wave binary format based upon:
        // http://ccrma.stanford.edu/CCRMA/Courses/422/projects/WaveFormat/

        // Calculate some values for new wav file
        $byteRate   = (int)($sampleRate * $channels * ($bits / 8));
        $blockAlign = (int)($channels * ($bits / 8));

        // Generate the new wav file

        // Add riff block
        $newWavFile = pack('A4lA4', 'RIFF', 36 + (count($data) * ($bits / 8)),
                           'WAVE');

        // Add fmt block
        $newWavFile .= pack('A4lvv', 'fmt', 16, 1, $channels);
        $newWavFile .= pack('VVvv', $sampleRate, $byteRate, $blockAlign, $bits);

        // Add data block
        $newWavFile .= pack('A4V', 'data', count($data) * ($bits / 8));

        $bitPackType = $this->_getPackingType($bits);

        foreach ($data as $d) {
            $newWavFile.= pack($bitPackType, (int)$d);
        }

        // return string containing binary of new wav file
        return $newWavFile;
    }

    /**
     * Generates and echoes an audio wav file
     *
     * This methods outputs an HTTP Content-type header
     *
     * @param string $text character string to spell out
     *
     * @return boolean TRUE or PEAR_Error on error
     * @access public
     */
    function output($text)
    {
        $wav = $this->getAudio($text);
        if (PEAR::isError($wav)) {
            return $wav;
        }

        header('Content-type: '.$this->_options['content_type']);
        echo $wav;

        return true;
    }

    /**
     * Get the expected wav filename containing the sound of a character
     *
     * The filename is either '[A-Za-z0-9].wav' or,
     * '[ASCII value of char].wav'.
     *
     * @param string $char Single character
     *
     * @return string Expected filename
     * @access public
     */
    function getFilename($char)
    {
        if (preg_match('#^[a-zA-Z]$#', $char)) {
            if ($this->_options['fold_cases']) {
                $filename = strtolower($char);
            } else {
                $filename = $char;
            }
        } elseif (preg_match('#^[0-9]$#', $char)) {
            $filename = $char;
        } elseif ($char == 'CAPITAL') {
            $filename = $char;
        } else {
            $filename = ord($char);
        }

        return $filename.'.wav';
    }

    /**
     * Returns array representing a wav file from a single character
     *
     * {$char}.wav is opened, parsed, and returned as an array.
     * The array contains elements:
     * <ul>
     *  <li>'char'       - function parameter $char</li>
     *  <li>'sampleRate' - sample rate (e.g. 16000)</li>
     *  <li>'bits'       - bits per sample (e.g. 16)</li>
     *  <li>'channels'   - channel count (1=mono, 2=stereo...) (e.g. 1)</li>
     *  <li>'data'       - array indexed from 1 of sound data</li>
     * </ul>
     *
     * @param string $char Single character
     *
     * @return array Array of wav properties or PEAR_Error object on error
     * @access private
     */
    function _getDecodedWav($char)
    {
        // Cache files
        static $wav = array();
        $path = $this->_options['sound_dir'];
        if (isset($wav[$path][$char])) {
            return $wav[$path][$char];
        }

        // magic constants and wave binary format based upon:
        // http://ccrma.stanford.edu/CCRMA/Courses/422/projects/WaveFormat/

        // attempt to open wav file
        $charFile = $this->getFilename($char);
        $file = '';
        if (!($file = fopen($path.$charFile, 'rb'))) {
            $error = PEAR::raiseError('error opening "'.$charFile.'"');
            return $error;
        }

        // read and check the RIFF header
        $riffBinDescriptor = fread($file, 12);
        $riffDescriptor    = unpack('A4riff/llength/A4type',
                                    $riffBinDescriptor);

        if ($riffDescriptor['riff'] != 'RIFF'
            || $riffDescriptor['type'] != 'WAVE') {
            $error = PEAR::raiseError('"'.$charFile.'" is not a wave file');
            return $error;
        }

        // long unpack argument for wav 'fmt' descriptor
        $chunkFormat  = 'A4chunkid/lsize/vformat/vnumchannels';
        $chunkFormat .= '/Vsamplerate/Vbyterate/vblockalign/vbitspersample';

        // read what we hope is the 'fmt' descriptor (basic wave properties)
        $chuckBinDescriptor = fread($file, 24);
        $chunkDescriptor = unpack($chunkFormat, $chuckBinDescriptor);

        // check we have the 'fmt' descriptor
        if ($chunkDescriptor['chunkid'] != 'fmt') {
            return $this->_wavFormatError($charFile);
        }

        if ($chunkDescriptor['format'] != 1) {
            return $this->_wavFormatError($charFile,
                                          'contains unsupported compression');
        }

        // wav files may contain extra descriptor information, ignore it
        $extraBytes = $chunkDescriptor['size'] - 16;
        if ($extraBytes > 0) {
            fread($file, $extraBytes);
        }

        // load the data chunk header
        // the data chunk header contains the number of bytes of sound
        // the wav file actually contains
        $dataBinDescriptor = fread($file, 8);
        $dataDescriptor = unpack('A4chunkid/Vsize', $dataBinDescriptor);

        // sanity check
        if ($dataDescriptor['chunkid'] != 'data'
            || $dataDescriptor['size'] < 0) {
            return $this->_wavFormatError($charFile, '');
        }

        $bitUnpackType = $this->_getPackingType($chunkDescriptor['bitspersample']);
        if (PEAR::isError($bitUnpackType)) {
            return $bitUnpackType;
        }

        // load wav data into an array
        // note: data is indexed from 1 not 0 due to unpack() implementation
        $dataBinData = fread($file, $dataDescriptor['size']);
        $data = unpack($bitUnpackType.'*', $dataBinData);

        // close file, ignore errors
        @fclose($file);

        // build return array
        $result = array();
        $result['char']       = $char;
        $result['sampleRate'] = $chunkDescriptor['samplerate'];
        $result['bits']       = $chunkDescriptor['bitspersample'];
        $result['channels']   = $chunkDescriptor['numchannels'];
        $result['data']       = $data;

        // Cache
        $wav[$path][$char] = $result;

        return $result;
    }

    /**
     * Returns a PEAR_Error about a wav file
     *
     * @param string Filename of wav file
     * @param string Optional error message
     * @return PEAR_Error
     * @access private
     */
    function _wavFormatError($charFile, $message = '')
    {
        $msg = '"'.$charFile.'" is an unsupported format or invalid wav file'
               .(($message == '') ? '' : (', '.$message));
        $error = PEAR::raiseError($msg);
        return $error;
    }

    /**
     * Returns the packing type for (un)pack() for a bit property
     *
     * The data in a wav file is stored in different formats depending
     * on the bit rate, this function returns the correct parameter for the
     * pack()/unpack() functions to use given the bits property of a wav file.
     *
     * @param $bits Number of bits property of wav file
     *
     * @return string String parameter for pack()/unpack() or PEAR_Error
     * @access private
     */
    function _getPackingType($bits)
    {
        switch ($bits) {
        case 8:
            return 'C';
            break;

        case 16:
            return 's';
            break;
        }
        $error = PEAR::raiseError('unsupported bit count in wav file');
        return $error;
    }

    /**
     * Converts a string to an array of single characters
     *
     * @param $string String to be converted
     * @return mixed Array of single characters
     * @access private
     */
    function _stringSplit($string)
    {
        if (function_exists('str_split')) {
            return str_split($string, 1);
        }

        $result = array();
        $len = strlen($string);
        for ($i = 0; $i < $len; ++$i) {
            $result[] = $string{$i};
        }
        return $result;
    }

    /**
     * Distorts the sound file
     *
     * @param array $data
     * @param integer $sampleRate
     * @param integer $channels
     *
     * @return array distorted data
     * @access protected
     */
    function _distort($data, $sampleRate, $channels)
    {
        for ($i = 1; $i < count($data); ++$i) {
            $data[count($data) - $i] += $data[$i] * 0.2;
        }
        return $data;
    }

    /**
     * Adds echo
     *
     * @param array $data
     * @param integer $sampleRate
     * @param integer $channels
     *
     * @return array sound clip with echo
     * @access protected
     */
    function _echo($data, $sampleRate, $channels)
    {
        // add a 0.4s echo to the file
        $x = $sampleRate * $channels * 0.4;
        for ($i = $x; $i < count($data); ++$i) {
            $data[$i] += $data[$i - $x] * 0.2;
        }
        return $data;
    }

    /**
     * Returns whether a character is to be considered capitalized
     *
     * @param string $char character to check
     *
     * @return boolean TRUE is capital, FALSE otherwise
     * @access protected
     */
    function _isCapital($char)
    {
        return strtoupper($char) === $char && strtolower($char) !== $char;
    }
}

?>