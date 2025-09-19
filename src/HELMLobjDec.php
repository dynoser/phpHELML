<?php
namespace dynoser\HELML;

use dynoser\HELML\vc85;

/*
 * This code represents a PHP implementation of the HELMLobjDec class.
 * 
 * The class provides object-oriented approach for decoding HELML-formatted data.
 * 
 */
class HELMLobjDec {
        
    /**
     * Массив рабочих данных
     * @var array
     */
    public $dataArr = [];

    /**
     * Массив слоев
     * @var array
     */
    public $_layersArr = [];
    
    /**
     * Массив строк HELML-данных
     * @var array
     */
    public $HELMLrowsArr = [];

    /**
     * Символ уровня вложенности
     * @var string
     */
    public $lvlCh = ':';

    /**
     * Символ пробела для значений
     * @var string
     */
    public $spcCh = ' ';

    // Custom user-specified values may be added here:
    public $SPEC_TYPE_VALUES = [
        'N' => null,
        'U' => null,
        'T' => true,
        'F' => false,
        'NAN' => NAN,
        'INF' => INF,
        'NIF' =>-INF,
    ];
    
    /**
     * Символ пробела для URL-стиля
     * @var string
     */
    public $URL_SPC = '=';

    /**
     * Символ уровня для URL-стиля
     * @var string
     */
    public $URL_LVL = '.';

    //Custom hooks below (set callable if need)
    
    // Hook for decode "  Value"
    public $CUSTOM_FORMAT_DECODER = null;
    
    // Enable auto-create array when key already exists
    public $ENABLE_DBL_KEY_ARR = false;


    /**
     * Конструктор класса
     */
    public function __construct() {
        // пока не требуется никаких действий
    }

    /**
     * Парсит HELML-данные и помещает их в массив HELMLrowsArr
     * 
     * @param string|array $srcRows
     * @return void
     * @throws InvalidArgumentException
     */
    public function setHELML($srcRows): void {

        $this->lvlCh = ':';
        $this->spcCh = ' ';
        
        // If the input is an array, use it. Otherwise, split the input string into an array.
        if (\is_array($srcRows)) {
            $this->HELMLrowsArr = $srcRows;
        } elseif (\is_string($srcRows)) {
            // Search postfix
            $pfPos = \strpos($srcRows, '~#'); //~#: ~
            if ($pfPos >= 0 && \substr($srcRows, $pfPos + 4, 1) === '~') {
                // get control-chars from postfix
                $this->lvlCh = $srcRows[$pfPos + 2];
                $this->spcCh = $srcRows[$pfPos + 3];
            } else {
                $pfPos = 0;
            }

            if (!$pfPos && \substr($srcRows, -1) === '~') {
                $this->lvlCh = $this->URL_LVL;
                $this->spcCh = $this->URL_SPC;
            }

            // Replace all ~ to line divider
            $srcRows = \strtr($srcRows, '~', "\n");
            
            // Detect Line Divider
            foreach(["\n", "\r"] as $explCh) {
                if (false !== \strpos($srcRows, $explCh)) {
                    break;
                }
            }
            $this->HELMLrowsArr = \explode($explCh, $pfPos ? \substr($srcRows, 0, $pfPos) : $srcRows);
        } else {
            throw new \InvalidArgumentException("Array or String required");
        }
    }

    /**
     * Выполняет декодирование HELML по массиву HELMLrowsArr с поддержкой слоев
     * 
     * Эта функция парсит HELML-данные построчно, создавая иерархическую структуру данных
     * с поддержкой слоев (layers) и вложенности. Слои позволяют группировать данные
     * и выбирать только нужные слои для декодирования.
     * 
     * @param array $layersListSrc Массив номеров слоев для декодирования (по умолчанию [0])
     * @return void
     */
    public function getLayers($layersListSrc = [0]): void {
        // Инициализация результирующего массива и стека для отслеживания вложенности
        $this->dataArr = [];             // Основной массив с декодированными данными
        $stack = [];                     // Стек ключей для навигации по вложенным массивам
        
        // Проверяем, что есть данные для обработки
        if (empty($this->HELMLrowsArr)) {
            // выходим, результатом будет пустой массив $dataArr
            return;
        }

        // Получаем ссылки на рабочие переменные для удобства
        $strArr = & $this->HELMLrowsArr;  // Массив строк HELML-данных
        $lvlCh = $this->lvlCh;           // Символ уровня вложенности (обычно ':')
        $spcCh = $this->spcCh;           // Символ пробела для значений (обычно ' ')
        
        // Инициализация переменных для работы со слоями
        $layerInit = 0;                  // Начальный номер слоя
        $layerCurr = $layerInit;         // Текущий активный слой
        $layersList = \array_flip($layersListSrc); // Преобразуем массив слоев в ассоциативный для быстрого поиска
        $allLayers = [$layerInit => 1];  // Массив всех найденных слоев
        $this->_layersArr = [$layerInit]; // Массив слоев для внешнего доступа



        // Переменные для управления уровнями вложенности
        $minLevel = -1;                  // Минимальный уровень вложенности в текущем блоке
        $baseLevel = 0;                  // Базовый уровень для корректировки вложенности
        
        // Основной цикл обработки каждой строки HELML-данных
        $linesCnt = \count($strArr);
        for ($lNum = 0; $lNum < $linesCnt; $lNum++) {
            $line = \trim($strArr[$lNum]); // Убираем пробелы в начале и конце строки

            // Пропускаем пустые строки и комментарии (начинающиеся с '#' или '//')
            if (!\strlen($line) || \substr($line, 0, 1) === '#' || \substr($line, 0, 2) === '//') continue;

            // Вычисляем уровень вложенности, подсчитывая количество символов уровня в начале строки
            $level = 0;
            while (\substr($line, $level, 1) === $lvlCh) {
                $level++;
            }

            // Удаляем символы уровня из начала строки, если они есть
            if ($level) {
                $line = \substr($line, $level);
            }

            // Разделяем строку на ключ и значение (если есть символ уровня)
            $parts = \explode($lvlCh, $line, 2);
            $key = $parts[0] ? $parts[0] : '0';        // Ключ (по умолчанию '0' если пустой)
            $value = $parts[1] ?? null; // Значение (null если нет символа уровня)

            // Корректируем уровень с учетом базового уровня
            $level += $baseLevel;
            
            // Обработка специальных команд управления базовым уровнем
            if (!$value) {
                if ($key === '<<') {
                    // Уменьшаем базовый уровень (выходим из блока)
                    $baseLevel && $baseLevel--;
                    continue;
                } elseif ($key === '>>') {
                    // Увеличиваем базовый уровень (входим в блок)
                    $baseLevel++;
                    continue;
                }
            } elseif ($value === '>>') {
                // Специальная команда в значении - увеличиваем базовый уровень
                $baseLevel++;
                $value = '';
            }

            // Отслеживаем минимальный уровень вложенности для корректной работы стека
            if ($minLevel < 0 || $minLevel > $level) {
                $minLevel = $level;
            }

            // Удаляем лишние ключи из стека, если уровень вложенности уменьшился
            $extraKeysCnt = \count($stack) - $level + $minLevel;
            if ($extraKeysCnt > 0) {
                // Удаляем лишние ключи из стека
                while(\count($stack) && $extraKeysCnt--) {
                    \array_pop($stack);
                }
                $layerCurr = $layerInit; // Сбрасываем текущий слой
            }

            // Находим родительский элемент в результирующем массиве для текущего ключа
            $parent = &$this->dataArr;
            foreach ($stack as $parentKey) {
                $parent = &$parent[$parentKey];
            }
            
            // Обработка специальных ключей, начинающихся с символа '-'
            if ('-' === \substr($key, 0, 1)) {
                if ($key === '--') {
                    // Автоматическое создание следующего числового ключа
                    $key = \count($parent);
                } elseif ($key === '-+' || $key === '-++' || $key === '---') {
                    // Ключи управления слоями
                    if (\is_string($value)) {
                        $value = \trim($value);
                    }
                    if ($key === '-++') {
                        // Установка начального слоя
                        $layerInit = $value ? $value : '0';
                        $layerCurr = $layerInit;
                    } elseif ($key === '-+') {
                        // Переключение на следующий слой
                        $layerCurr = ($value || $value === '0') ? $value : (\is_numeric($layerCurr) ? ($layerCurr + 1) : 0);
                    }
                    $allLayers[$layerCurr] = 1; // Добавляем слой в список всех слоев
                    continue;
                } else {
                    // Декодирование ключа из base64url (если начинается с '-')
                    $decodedKey = self::base64Udecode(\substr($key, 1));
                    if (false !== $decodedKey) {
                        $key = $decodedKey;
                    }
                }
            }

            // Обработка значения в зависимости от его типа
            if (\is_null($value) || $value === '') {
                // Если значение пустое - создаем новый массив и добавляем ключ в стек
                $parent[$key] = [];
                \array_push($stack, $key);
            } elseif (\array_key_exists($layerCurr, $layersList)) {
                // Обрабатываем значение только если текущий слой входит в список для декодирования
                
                // Обработка многострочных литералов
                if ($value === '`' || $value === '<') {
                    $endChar = ($value === '<') ? '>' : '`'; // Определяем символ окончания
                    $value = [];
                    // Читаем строки до символа окончания
                    for($cln = $lNum + 1; $cln < $linesCnt; $cln++) {
                        $line = \trim($strArr[$cln],"\r\n\x00");
                        if (\trim($line) === $endChar) {
                            $value = \implode("\n", $value); // Объединяем строки
                            $lNum = $cln; // Пропускаем обработанные строки
                            break;
                        }
                        $value[] = $line;
                    }
                    if (\is_string($value)) {
                        if ($endChar === '>') {
                            // Декодируем vc85-кодированные данные
                            $value = vc85::decode($value);
                        }
                    } else {
                        $value = '`ERR`'; // Ошибка при обработке многострочного литерала
                    }
                } else {
                    // Используем функцию декодер значений
                    $value = $this->valueDecode($value);
                }
               
                // Обработка дублирующихся ключей
                if ($this->ENABLE_DBL_KEY_ARR && \array_key_exists($key, $parent)) {
                    // Если включена поддержка массивов для дублирующихся ключей
                    if (\is_array($parent[$key])) {
                        $parent[$key][] = $value; // Добавляем в существующий массив
                    } else {
                        $parent[$key] = [$parent[$key], $value]; // Создаем массив из существующего значения и нового
                    }
                } else {
                    // Обычное добавление пары ключ-значение в текущий массив
                    $parent[$key] = $value;
                }
            }
        }
        
        // Обновляем список слоев, если найдено больше одного слоя
        if (\count($allLayers) > 1) {
            $this->_layersArr = \array_keys($allLayers);
        }
    }

    /**
     * Декодирует HELML-форматированную строку или массив в ассоциативный массив
     * Результат сохраняется в свойство $dataArr
     * 
     * @param string|array $srcRows
     * @param array $layersListSrc
     * @return void
     * @throws InvalidArgumentException
     */
    public function decode($srcRows, $layersListSrc = [0]): void {
        $this->setHELML($srcRows);
        $this->getLayers($layersListSrc);
    }

    /**
     * Decode an encoded value based on its prefix
     * 
     * @param string $encodedValue
     * @return mixed
     */
    public function valueDecode($encodedValue): mixed {
        $fc = \substr($encodedValue, 0, 1);
        if ('-' === $fc || '+' === $fc) {
            $encodedValue = self::base64Udecode(\substr($encodedValue, 1));
            if ('-' === $fc) {
                return $encodedValue;
            }
            $fc = \substr($encodedValue, 0, 1);
        }
        $spcCh = $this->spcCh;
        if ($spcCh === $fc) {
            if (\substr($encodedValue, 0, 2) !== $spcCh . $spcCh) {
                // if the string starts with only one space, return the string after it
                return \substr($encodedValue, 1);
            }
            // if the string starts with two spaces, then it encodes a non-string value
            $slicedValue = \substr($encodedValue, 2); // strip left spaces
            if (\is_numeric($slicedValue)) {
                // it's probably a numeric value
                if (\strpos($slicedValue, '.')) {
                    // if there's a decimal point, it's a floating point number
                    return (double) $slicedValue;
                }
                // if there's no decimal point, it's an integer
                return (int) $slicedValue;
            } elseif (\array_key_exists($slicedValue, $this->SPEC_TYPE_VALUES)) {
                return $this->SPEC_TYPE_VALUES[$slicedValue];
            } elseif ($this->CUSTOM_FORMAT_DECODER) {
                return \call_user_func($this->CUSTOM_FORMAT_DECODER, $encodedValue);
            }            
            return $encodedValue;
        } elseif ('"' === $fc || "'" === $fc) {
            $encodedValue = \substr($encodedValue, 1, -1); // trim the presumed quotes at the edges and return the interior
            if ("'" === $fc) {
                return $encodedValue;
            }
            return \stripcslashes($encodedValue);
        } elseif ('`' === $fc) {
            return \substr($encodedValue, 2, -2);
        } elseif ('<' === $fc) {
            return vc85::decode($encodedValue);
        } elseif ('%' === $fc) {
            return self::hexDecode(\substr($encodedValue, 1));
        }

        // if there are no spaces or quotes or "-" at the beginning
        if ($this->CUSTOM_FORMAT_DECODER) {
            return \call_user_func($this->CUSTOM_FORMAT_DECODER, $encodedValue);
        }
        
        // Для обычных строк без префиксов возвращаем как есть
        return $encodedValue;
    }
    
    /**
     * Decode a base64url encoded string
     * 
     * @param string $str
     * @return string|false
     */
    public static function base64Udecode($str): string|false {
        return \base64_decode(\strtr($str, '-_', '+/'));
    }
    
    /**
     * Decode a hex encoded string
     * 
     * @param string $str
     * @return string|false
     */
    public static function hexDecode($str): string|false {
        $hexArr = [];
        $l = \strlen($str);
        for($i = 0; $i < $l; $i++) {
            $ch1 = $str[$i];
            if (\ctype_xdigit($ch1)) {
                $ch2 = \substr($str, $i+1, 1);
                if (\ctype_xdigit($ch2)) {
                    $hexArr[] = $ch1 . $ch2;
                    $i++;
                } else {
                    $hexArr[] = '0' . $ch1;
                }
            }
        }
        return \hex2bin(\implode('', $hexArr));
    }
}
