<?php
# based on code from lrvick and LiquidCrystal
# lrvic - https://github.com/lrvick/raspi-hd44780/blob/master/hd44780.py
# LiquidCrystal - https://github.com/arduino/Arduino/blob/master/libraries/LiquidCrystal/LiquidCrystal.cpp

namespace lcdgpio;
use PhpGpio\Gpio;
class lcd
{
    # commands
    const LCD_CLEARDISPLAY              = 0x01;
    const LCD_RETURNHOME                = 0x02;
    const LCD_ENTRYMODESET              = 0x04;
    const LCD_DISPLAYCONTROL            = 0x08;
    const LCD_CURSORSHIFT               = 0x10;
    const LCD_FUNCTIONSET               = 0x20;
    const LCD_SETCGRAMADDR              = 0x40;
    const LCD_SETDDRAMADDR              = 0x80;

    # flags for display entry mode
    const LCD_ENTRYRIGHT                = 0x00;
    const LCD_ENTRYLEFT                 = 0x02;
    const LCD_ENTRYSHIFTINCREMENT       = 0x01;
    const LCD_ENTRYSHIFTDECREMENT       = 0x00;

    # flags for display on/off control
    const LCD_DISPLAYON                 = 0x04;
    const LCD_DISPLAYOFF                = 0x00;
    const LCD_CURSORON                  = 0x02;
    const LCD_CURSOROFF                 = 0x00;
    const LCD_BLINKON                   = 0x01;
    const LCD_BLINKOFF                  = 0x00;

    # flags for display/cursor shift
    const LCD_DISPLAYMOVE               = 0x08;
    const LCD_CURSORMOVE                = 0x00;

    # flags for display/cursor shift
    const LCD_MOVERIGHT                 = 0x04;
    const LCD_MOVELEFT                  = 0x00;

    # flags for function set
    const LCD_8BITMODE                  = 0x10;
    const LCD_4BITMODE                  = 0x00;
    const LCD_2LINE                     = 0x08;
    const LCD_1LINE                     = 0x00;
    const LCD_5x10DOTS                  = 0x04;
    const LCD_5x8DOTS                   = 0x00;
    
    const NEXT_LINE                     = 0xC0;

    private $pin_rs;
    private $pin_e;
    private $pins_db;
    private $pin_db_reverse;
    private $displaycontrol;
    private $displayfunction;
    public function getGpio()
    {
        static $gpio;
        if( empty($gpio) )
            $gpio = new GPIO();
        return $gpio;
    }

    public function __construct($pin_rs=14,$pin_e=15,$pins_db=[17, 18, 27, 22])
    {
        $this->pin_rs      = $pin_rs;
        $this->pin_e     = $pin_e;
        $this->pins_db    = $pins_db;
        $this->pins_db_reverse = array_reverse($pins_db);
        $gpio = $this->getGpio();
        $gpio->setup($this->pin_e, 'out');
        $gpio->setup($this->pin_rs, 'out');
        foreach( $this->pins_db as $pin)
            $gpio->setup($pin, 'out');
    
        $this->write4bits(0x33); # initialization
        $this->write4bits(0x32); # initialization
        $this->write4bits(0x28); # 2 line 5x7 matrix
        $this->write4bits(0x0C); # turn cursor off 0x0E to enable cursor
        $this->write4bits(0x06); # shift cursor right
        
        $this->displaycontrol = self::LCD_DISPLAYON | self::LCD_CURSOROFF | self::LCD_BLINKOFF;
        
        $this->displayfunction = self::LCD_4BITMODE | self::LCD_1LINE | self::LCD_5x8DOTS;
        $this->displayfunction |= self::LCD_2LINE;

        // Initialize to default text direction (for romance languages) 
        $this->displaymode =  self::LCD_ENTRYLEFT | self::LCD_ENTRYSHIFTDECREMENT;
        $this->write4bits(self::LCD_ENTRYMODESET | $this->displaymode); #  set the entry mode

        $this->clear();
        return $this;
    }
    public function __destruct()
    {
        $this->getGpio()->unexportAll();
    }
    public function begin($cols, $lines)
    {
        if ($lines > 1)
        {
            $this->numlines = $lines;
            $this->displayfunction |= self::LCD_2LINE;
            $this->currline = 0;
        }
    }
    public function home()
    {
        $this->write4bits(self::LCD_RETURNHOME); # set cursor position to zero
        $this->delayMicroseconds(3000); # this command takes a long time!
    }
    public function clear()
    {
        $this->write4bits(self::LCD_CLEARDISPLAY); # command to clear display
        $this->delayMicroseconds(3000);    # 3000 microsecond sleep, clearing the display takes a long time
    }
    public function setCursor( $col, $row)
    {
        $this->row_offsets = [ 0x00, 0x40, 0x14, 0x54 ];
        
        if ( $row > $this->numlines )
            $row = $this->numlines - 1; # we count rows starting w/0
        
        $this->write4bits(self::LCD_SETDDRAMADDR | ($col + $this->row_offsets[$row]));
    }
    public function noDisplay()
    {
        // Turn the display off (quickly) 
        $this->displaycontrol &= ~self::LCD_DISPLAYON;
        $this->write4bits(self::LCD_DISPLAYCONTROL | $this->displaycontrol);
    }
    public function display()
    {
        // Turn the display on (quickly) 
        $this->displaycontrol |= self::LCD_DISPLAYON;
        $this->write4bits(self::LCD_DISPLAYCONTROL | $this->displaycontrol);
    }
    public function noCursor()
    {
        // Turns the underline cursor on/off 
        $this->displaycontrol &= ~self::LCD_CURSORON;
        $this->write4bits(self::LCD_DISPLAYCONTROL | self::displaycontrol);
    }
    public function cursor()
    {
        // Cursor On 
        $this->displaycontrol |= self::LCD_CURSORON;
        $this->write4bits(self::LCD_DISPLAYCONTROL | $this->displaycontrol);
    }
    public function noBlink()
    {
        // Turn on and off the blinking cursor 
        $this->displaycontrol &= ~self::LCD_BLINKON;
        $this->write4bits(self::LCD_DISPLAYCONTROL | $this->displaycontrol);
    }
    public function DisplayLeft()
    {
        // These commands scroll the display without changing the RAM 
        $this->write4bits(self::LCD_CURSORSHIFT | self::LCD_DISPLAYMOVE | self::LCD_MOVELEFT);
    }

    public function scrollDisplayRight()
    {
        // These commands scroll the display without changing the RAM 
        $this->write4bits(self::LCD_CURSORSHIFT | self::LCD_DISPLAYMOVE | self::LCD_MOVERIGHT);
    }
    public function leftToRight()
    {
        // This is for text that flows Left to Right 
        $this->displaymode |= self::LCD_ENTRYLEFT;
        $this->write4bits(self::LCD_ENTRYMODESET | $this->displaymode);
    }

    public function rightToLeft()
    {
        // This is for text that flows Right to Left 
        $this->displaymode &= ~self::LCD_ENTRYLEFT;
        $this->write4bits(self::LCD_ENTRYMODESET | $this->displaymode);
    }
    public function autoscroll()
    {
        // This will 'right justify' text from the cursor 
        $this->displaymode |= self::LCD_ENTRYSHIFTINCREMENT;
        $this->write4bits(self::LCD_ENTRYMODESET | $this->displaymode);
    }

    public function noAutoscroll()
    {
        // This will 'left justify' text from the cursor 
        
        $this->displaymode &= ~self::LCD_ENTRYSHIFTINCREMENT;
        $this->write4bits(self::LCD_ENTRYMODESET | $this->displaymode);
    }
    public function bin($input)
    {
        return decbin($input);
    }
    public function write4bits($bits, $char_mode=0)
    {
        // Send command to LCD 
        $this->delayMicroseconds(100); # 1000 microsecond sleep
        $bits=str_pad($this->bin($bits),8,'0', STR_PAD_LEFT);

        $this->getGpio()->output($this->pin_rs, $char_mode);
        foreach( $this->pins_db as $pin)
            $this->getGpio()->output($pin, 0);

        foreach( range(0,3) as $i)
            if( $bits[$i] == 1 )
                $this->getGpio()->output($this->pins_db_reverse[$i], 1);

        $this->pulseEnable();
        
        foreach( $this->pins_db as $pin)
            $this->getGpio()->output($pin, 0);

        foreach( range(4,7) as $i)
            if( $bits[$i] == 1 )
                $this->getGpio()->output($this->pins_db_reverse[$i-4], 1);

        $this->pulseEnable();
    }

    public function delayMicroseconds($microseconds)
    {
        usleep($microseconds);
    }

    public function pulseEnable()
    {
        $this->getGpio()->output($this->pin_e, 0);
        $this->delayMicroseconds(1);        # 1 microsecond pause - enable pulse must be > 450ns 
        $this->getGpio()->output($this->pin_e, 1);
        $this->delayMicroseconds(1);        # 1 microsecond pause - enable pulse must be > 450ns 
        $this->getGpio()->output($this->pin_e, 0);
        $this->delayMicroseconds(1);    # commands need > 37us to settle
    }

    public function message($texte)
    {
        // Send string to LCD. Newline wraps to second line
        foreach( range(0, strlen($texte)-1) as $n_char )
        {
            if ( $texte[$n_char] == "\n" )
                $this->write4bits(self::NEXT_LINE); # next line
            else
                $this->write4bits(ord($texte[$n_char]),1);
        }
    }
}