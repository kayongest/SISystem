<?php
/**
 * Pure-PHP ZipArchive polyfill using built-in zlib (gzinflate / gzdeflate)
 * Provides seamless .xlsx and .zip read/write support when PHP's ext-zip is not enabled in php.ini
 */
if (!class_exists('ZipArchive')) {
    class ZipArchive
    {
        public const CREATE = 1;
        public const EXCL = 2;
        public const CHECKCONS = 4;
        public const OVERWRITE = 8;
        public const FL_NOCASE = 1;
        public const FL_NODIR = 2;
        public const FL_COMPRESSED = 4;
        public const FL_UNCHANGED = 8;
        public const CM_DEFAULT = -1;
        public const CM_STORE = 0;
        public const CM_DEFLATE = 8;

        public $numFiles = 0;
        public $filename = '';
        private $entries = [];
        private $writeEntries = [];
        private $rawData = '';
        private $isWriting = false;

        public function open(string $filename, int $flags = 0)
        {
            $this->filename = $filename;
            $this->entries = [];
            $this->writeEntries = [];
            $this->rawData = '';
            $this->numFiles = 0;

            if (($flags & self::OVERWRITE) !== 0 || (($flags & self::CREATE) !== 0 && !is_file($filename))) {
                $this->isWriting = true;
                return true;
            }

            if (!is_file($filename) || !is_readable($filename)) {
                if (($flags & self::CREATE) !== 0) {
                    $this->isWriting = true;
                    return true;
                }
                return false;
            }

            $this->isWriting = false;
            $this->rawData = @file_get_contents($filename);
            if ($this->rawData === false || strlen($this->rawData) < 22) {
                if (($flags & self::CREATE) !== 0) {
                    $this->isWriting = true;
                    return true;
                }
                return false;
            }

            $this->parseCentralDirectory();
            $this->numFiles = count($this->entries);
            return true;
        }

        private function parseCentralDirectory(): void
        {
            $len = strlen($this->rawData);
            $maxSearch = min($len, 65557);
            $eocdPos = strrpos(substr($this->rawData, $len - $maxSearch), "PK\x05\x06");
            if ($eocdPos === false) {
                return;
            }
            $eocdPos += ($len - $maxSearch);

            $eocd = unpack('vdisk/vcdDisk/vdiskEntries/vtotalEntries/VcdSize/VcdOffset', substr($this->rawData, $eocdPos + 4, 16));
            if (!$eocd) {
                return;
            }

            $offset = $eocd['cdOffset'];
            $total = $eocd['totalEntries'];

            for ($i = 0; $i < $total && $offset + 46 <= $len; $i++) {
                if (substr($this->rawData, $offset, 4) !== "PK\x01\x02") {
                    break;
                }
                $hdr = unpack(
                    'vverMade/vverNeed/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                    substr($this->rawData, $offset + 4, 42)
                );
                $name = substr($this->rawData, $offset + 46, $hdr['nameLen']);
                $normName = str_replace('\\', '/', $name);

                $this->entries[] = [
                    'index' => $i,
                    'name' => $normName,
                    'method' => $hdr['method'],
                    'compSize' => $hdr['compSize'],
                    'uncompSize' => $hdr['uncompSize'],
                    'crc' => $hdr['crc'],
                    'localOffset' => $hdr['localOffset'],
                ];

                $offset += 46 + $hdr['nameLen'] + $hdr['extraLen'] + $hdr['commentLen'];
            }
        }

        public function addFromString(string $name, string $content, int $flags = 0): bool
        {
            $this->isWriting = true;
            $normName = ltrim(str_replace('\\', '/', $name), '/');
            $this->writeEntries[$normName] = $content;
            $this->numFiles = count($this->writeEntries);
            return true;
        }

        public function addFile(string $filepath, string $entryname = '', int $start = 0, int $length = 0, int $flags = 0): bool
        {
            if (!is_file($filepath) || !is_readable($filepath)) {
                return false;
            }
            $content = @file_get_contents($filepath);
            if ($content === false) {
                return false;
            }
            $targetName = $entryname !== '' ? $entryname : basename($filepath);
            return $this->addFromString($targetName, $content, $flags);
        }

        public function addEmptyDir(string $dirname, int $flags = 0): bool
        {
            $normName = rtrim(ltrim(str_replace('\\', '/', $dirname), '/'), '/') . '/';
            return $this->addFromString($normName, '');
        }

        public function setCompressionName(string $name, int $method, int $compflags = 0): bool
        {
            return true;
        }

        public function setCompressionIndex(int $index, int $method, int $compflags = 0): bool
        {
            return true;
        }

        public function getStatusString(): string
        {
            return 'No error';
        }

        public function locateName(string $name, int $flags = 0)
        {
            $norm = ltrim(str_replace('\\', '/', $name), '/');
            $noCase = ($flags & self::FL_NOCASE) !== 0;

            foreach ($this->entries as $idx => $entry) {
                $entryName = ltrim($entry['name'], '/');
                if ($noCase) {
                    if (strcasecmp($entryName, $norm) === 0) {
                        return $idx;
                    }
                } else {
                    if ($entryName === $norm) {
                        return $idx;
                    }
                }
            }
            return false;
        }

        public function statName(string $name, int $flags = 0)
        {
            $idx = $this->locateName($name, $flags);
            if ($idx === false) {
                return false;
            }
            return $this->statIndex($idx);
        }

        public function statIndex(int $index, int $flags = 0)
        {
            if (!isset($this->entries[$index])) {
                return false;
            }
            $e = $this->entries[$index];
            return [
                'name' => $e['name'],
                'index' => $e['index'],
                'crc' => $e['crc'],
                'size' => $e['uncompSize'],
                'mtime' => time(),
                'comp_size' => $e['compSize'],
                'comp_method' => $e['method'],
            ];
        }

        public function getFromName(string $name, int $length = 0, int $flags = 0)
        {
            $idx = $this->locateName($name, $flags);
            if ($idx === false) {
                $idx = $this->locateName($name, self::FL_NOCASE);
                if ($idx === false) {
                    return false;
                }
            }
            return $this->getFromIndex($idx, $length, $flags);
        }

        public function getFromIndex(int $index, int $length = 0, int $flags = 0)
        {
            if (!isset($this->entries[$index])) {
                return false;
            }
            $e = $this->entries[$index];
            $localOffset = $e['localOffset'];
            if (substr($this->rawData, $localOffset, 4) !== "PK\x03\x04") {
                return false;
            }
            $localHdr = unpack('vver/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($this->rawData, $localOffset + 4, 26));
            $dataOffset = $localOffset + 30 + $localHdr['nameLen'] + $localHdr['extraLen'];
            $compData = substr($this->rawData, $dataOffset, $e['compSize']);

            if ($e['method'] === 0) {
                $data = $compData;
            } elseif ($e['method'] === 8) {
                $data = @gzinflate($compData);
                if ($data === false) {
                    return false;
                }
            } else {
                return false;
            }

            if ($length > 0) {
                return substr($data, 0, $length);
            }
            return $data;
        }

        public function close(): bool
        {
            if ($this->isWriting && $this->filename !== '') {
                $localParts = '';
                $cdParts = '';
                $offset = 0;
                $count = 0;

                foreach ($this->writeEntries as $name => $data) {
                    $uncompSize = strlen($data);
                    $crc = crc32($data);
                    $compData = @gzdeflate($data, 6);
                    if ($compData === false || $uncompSize === 0) {
                        $method = 0;
                        $compData = $data;
                    } else {
                        $method = 8;
                    }
                    $compSize = strlen($compData);
                    $nameLen = strlen($name);

                    $localHeader = pack(
                        'VvvvvvVVVvv',
                        0x04034b50,
                        20,
                        0,
                        $method,
                        0,
                        0,
                        $crc,
                        $compSize,
                        $uncompSize,
                        $nameLen,
                        0
                    ) . $name;

                    $cdHeader = pack(
                        'VvvvvvvVVVvvvvvVV',
                        0x02014b50,
                        20,
                        20,
                        0,
                        $method,
                        0,
                        0,
                        $crc,
                        $compSize,
                        $uncompSize,
                        $nameLen,
                        0,
                        0,
                        0,
                        0,
                        32,
                        $offset
                    ) . $name;

                    $localParts .= $localHeader . $compData;
                    $cdParts .= $cdHeader;
                    $offset += strlen($localHeader) + $compSize;
                    $count++;
                }

                $eocd = pack(
                    'VvvvvVVv',
                    0x06054b50,
                    0,
                    0,
                    $count,
                    $count,
                    strlen($cdParts),
                    $offset,
                    0
                );

                @file_put_contents($this->filename, $localParts . $cdParts . $eocd);
            }

            $this->entries = [];
            $this->writeEntries = [];
            $this->rawData = '';
            $this->isWriting = false;
            return true;
        }
    }
}
