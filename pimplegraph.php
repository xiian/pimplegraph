<?php
class PimpleGraph
{
    protected $dependencies = [];

    protected $tokens;

    protected $pointer = 0;

    public function processContainer(\Pimple\Container $container) {
        foreach($container->keys() as $key) {
            $this->dependencies[$key] = [];
            $raw = $container->raw($key);
            if (!is_callable($raw)) {
                // Skip non-dependency inducing
                continue;
            }

            $ref = new \ReflectionFunction($raw);
            $this->tokens = $this->getFunctionDeclarationTokens($ref->getFileName(), $ref->getStartLine(), $ref->getEndLine());
            $this->dependencies[$key] = $this->extractDependencies('$' . $ref->getParameters()[0]->getName());
        }
    }

    public function getFunctionDeclarationTokens($file, $start, $end)
    {
        $fh    = fopen($file, 'r');
        $num   = 0;
        $lines = '';
        while (($line = fgets($fh)) && ($num < $end)) {
            if (++$num >= $start) {
                $lines .= trim($line) . ' ';
            }
        }
        $tokens = token_get_all('<?php ' . $lines . PHP_EOL . '// */');
        // Debug to make tokens easier to look at
        $tokens = array_map(function($i){
                if (is_array($i)) {
                    $i[] = token_name($i[0]);
                }
                return $i;
            }, $tokens);
        return $tokens;
    }

    public function curTok()
    {
        return $this->tokens[$this->pointer];
    }

    public function nextTok($skip = [T_WHITESPACE])
    {
        do {
            $this->pointer++;
        } while (in_array($this->curTok()[0], $skip));
        return $this->curTok();
    }

    private function extractDependencies($containerRef)
    {
        $depends = [];
        $c       = count($this->tokens);

        $this->pointer = 0;

        // Seek to the open bracket for the function
        while($this->curTok() != '{') {
            $this->nextTok();
        }
        $start = $this->pointer;

        for ($this->pointer = $start; $this->pointer < $c; $this->pointer++) {

            // If it's not our container object, don't bother.
            if (!is_array($this->curTok()) || $this->curTok()[0] != T_VARIABLE || $this->curTok()[1] != $containerRef) {
                continue;
            }
            $this->nextTok();

            // Close parens are death
            if ($this->curTok() == ')') {
                continue;
            }

            // Direct array access
            if ($this->curTok() == '[') {
                $depends[] = trim($this->nextTok()[1], "'");
                continue;
            }

            // Otherwise, hopefully it's object access
            if ($this->curTok()[0] != T_OBJECT_OPERATOR) {
                continue;
            }
            $this->nextTok();

            if ($this->curTok()[0] !== T_STRING || $this->curTok()[1] !== 'get') {
                continue;
            }

            $this->nextTok();
            if ($this->curTok() == '(') {
                $this->nextTok();
            }

            if ($this->curTok()[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $depends[] = trim($this->curTok()[1], "'");
        }
        $depends = array_unique($depends);
        return $depends;
    }

    public function toDot()
    {
        $output = 'digraph bobby {' . PHP_EOL;
        foreach ($this->dependencies as $k => $v) {
            $output .= array_reduce($v, function($out, $in) use ($k) {
                return $out . '"' . $k . '"' . ' -> "' . $in . '";' . PHP_EOL;
            });
        }
        return $output . '}';
    }
}
