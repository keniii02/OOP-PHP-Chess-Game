<?php
// Session Start
session_start();

//  GAME LOGIC AND STATE MANAGEMENT 

// Check if a move was submitted or if the game needs initialization
if (isset($_POST['reset'])) {
    //Destroy the old session entirely to guarantee a fresh start
    session_destroy();
    session_start(); 
    $_SESSION['chess_game'] = new ChessGame();
    $selected_piece = null;
    $possible_moves = [];
} else if (!isset($_SESSION['chess_game'])) {
    // Initialize game if session is new
    $_SESSION['chess_game'] = new ChessGame();
    $selected_piece = null;
    $possible_moves = [];
} else {
    /** @var ChessGame $game */
    $game = $_SESSION['chess_game'];

    if (isset($_POST['select_piece'])) {
        $from = $_POST['select_piece'];
        $possible_moves = $game->getMovesForPiece($from);
        if ($possible_moves) {
            $_SESSION['selected_piece'] = $from;
            $_SESSION['possible_moves'] = $possible_moves;
            $game->setMessage("Selected $from. Now choose a destination (one of the highlighted squares).");
        } else {
            $_SESSION['selected_piece'] = null;
            $_SESSION['possible_moves'] = [];
            $game->setMessage("Invalid selection or not your piece. Try again.");
        }
    }
    else if (isset($_POST['move_to']) && isset($_SESSION['selected_piece'])) {
        $from = $_SESSION['selected_piece'];
        $to = $_POST['move_to'];

        $game->makeMove($from, $to);

        unset($_SESSION['selected_piece']);
        unset($_SESSION['possible_moves']);
    }
    else if (isset($_POST['cancel'])) {
        unset($_SESSION['selected_piece']);
        unset($_SESSION['possible_moves']);
        $game->setMessage(ucfirst($game->getTurn()) . " to move. Selection cancelled.");
    }

    $_SESSION['chess_game'] = $game;
    $selected_piece = $_SESSION['selected_piece'] ?? null;
    $possible_moves = $_SESSION['possible_moves'] ?? [];
}

$game = $_SESSION['chess_game'];

//  OOP CLASSES: PIECE IMPLEMENTATIONS 

abstract class AbstractPiece {
    protected $color; 
    protected $position; 
    protected $symbol; 
    protected $hasMoved = false; // Track movement for castling/pawn moves

    public function __construct(string $color, string $position) {
        $this->color = $color;
        $this->position = $position;
    }

    abstract protected function getGeometricMoves(array $board): array;
    abstract public function getLegalMoves(array $board): array;

    public function getColor(): string { return $this->color; }
    public function getSymbol(): string { return $this->symbol; }
    public function getPosition(): string { return $this->position; }
    public function hasMoved(): bool { return $this->hasMoved; }

    public function setPosition(string $newPosition): void {
        $this->position = $newPosition;
        $this->hasMoved = true; // Mark piece as moved
    }

    //  POSITION CONVERSION HELPERS 
    protected function isOnBoard(int $x, int $y): bool {
        return $x >= 0 && $x < 8 && $y >= 0 && $y < 8;
    }
    protected function posToIndices(string $pos): array {
        $fileIndex = ord($pos[0]) - ord('a');
        $rankIndex = (int)$pos[1] - 1;
        return [$fileIndex, $rankIndex];
    }
    protected function indicesToPos(int $x, int $y): string {
        return chr(ord('a') + $x) . ($y + 1);
    }
    
    //  GENERIC SLIDING MOVEMENT CALCULATION (Rook, Bishop, Queen) 
    protected function calculateSlidingMoves(array $board, array $directions): array {
        $moves = [];
        [$fileIndex, $rankIndex] = $this->posToIndices($this->position);

        foreach ($directions as [$df, $dr]) {
            for ($i = 1; $i < 8; $i++) {
                $newFileIndex = $fileIndex + $df * $i;
                $newRankIndex = $rankIndex + $dr * $i;

                if (!$this->isOnBoard($newFileIndex, $newRankIndex)) {
                    break;
                }

                $targetPiece = $board[$newRankIndex][$newFileIndex] ?? null;
                $targetPos = $this->indicesToPos($newFileIndex, $newRankIndex);

                if ($targetPiece === null) {
                    $moves[] = $targetPos;
                } elseif ($targetPiece->getColor() !== $this->color) {
                    $moves[] = $targetPos;
                    break; 
                } else {
                    break; 
                }
            }
        }
        return $moves;
    }
    
    //  CORE KING SAFETY IMPLEMENTATION 

    protected function getKingPosition(array $board): ?string {
        foreach ($board as $rank) {
            foreach ($rank as $piece) {
                if ($piece instanceof King && $piece->getColor() === $this->color) {
                    return $piece->getPosition();
                }
            }
        }
        return null;
    }

    // Checks if a specific square is under attack by the opponent
    protected function isSquareAttacked(string $pos, array $board, string $attackingColor): bool {
        // We use the passed attacking color, not necessarily the opponent of $this->color
        $opponentColor = $attackingColor; 

        foreach ($board as $rank) {
            foreach ($rank as $piece) {
                if ($piece !== null && $piece->getColor() === $opponentColor) {
                    
                    if (method_exists($piece, 'getGeometricMoves')) {
                        // Pass $this->color as the dummy 'myColor' to prevent self-attack checks
                        $moves = $piece->getGeometricMoves($board, $this->color);
                        if (in_array($pos, $moves)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
    
    // Final check for all pieces: ensures a move doesn't leave the King in check
    protected function filterSafeMoves(array $geometricMoves, array $board): array {
        $safeMoves = [];
        $from = $this->position;
        $opponentColor = ($this->color === 'white') ? 'black' : 'white';

        foreach ($geometricMoves as $to) {
            // 1. Create a deep copy of the board for simulation
            $tempBoard = array_map(function($rank) {
                return array_map(function($piece) {
                    return $piece ? clone $piece : null;
                }, $rank);
            }, $board);
            
            // 2. Perform the simulated move
            [$fX, $fY] = $this->posToIndices($from);
            [$tX, $tY] = $this->posToIndices($to);

            $tempPiece = $tempBoard[$fY][$fX];
            
            // Castling Move Handling (King moves two squares)
            if ($tempPiece instanceof King && abs($fX - $tX) === 2) {
                // This is a castling move, which is checked in getCastlingMoves()
                $safeMoves[] = $to;
                continue; 
            }

            // Standard Move:
            $tempPiece->setPosition($to); 
            $tempBoard[$tY][$tX] = $tempPiece;
            $tempBoard[$fY][$fX] = null;
            
            //  Find the King's position on the simulated board
            $kingPos = $this->getKingPosition($tempBoard);

            //  Check if the King is attacked after the move
            if ($kingPos !== null && !$this->isSquareAttacked($kingPos, $tempBoard, $opponentColor)) {
                $safeMoves[] = $to;
            }
        }

        array_unshift($safeMoves, $from);
        return array_unique($safeMoves);
    }
}

// --- CONCRETE PIECE IMPLEMENTATIONS ---

class Rook extends AbstractPiece {
    protected $symbol = ['white' => '&#9814;', 'black' => '&#9820;'];

    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }
    
    protected function getGeometricMoves(array $board): array {
        $directions = [[0, 1], [0, -1], [1, 0], [-1, 0]]; // Straight lines
        return $this->calculateSlidingMoves($board, $directions);
    }

    public function getLegalMoves(array $board): array {
        $geometricMoves = $this->getGeometricMoves($board);
        return $this->filterSafeMoves($geometricMoves, $board);
    }
}

class Pawn extends AbstractPiece {
    protected $symbol = ['white' => '&#9817;', 'black' => '&#9823;'];

    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }

    protected function getGeometricMoves(array $board): array {
        $moves = [];
        [$fX, $fY] = $this->posToIndices($this->position);
        $direction = ($this->color === 'white') ? 1 : -1;
        $startRank = ($this->color === 'white') ? 1 : 6;

        //  Forward Movement
        $oneStepY = $fY + $direction;
        if ($this->isOnBoard($fX, $oneStepY) && ($board[$oneStepY][$fX] ?? null) === null) {
            $moves[] = $this->indicesToPos($fX, $oneStepY);

            // Check 2 Steps Forward
            if (!$this->hasMoved && $fY === $startRank) {
                $twoStepY = $fY + $direction * 2;
                if ($this->isOnBoard($fX, $twoStepY) && ($board[$twoStepY][$fX] ?? null) === null) {
                    $moves[] = $this->indicesToPos($fX, $twoStepY);
                }
            }
        }
        
        //  Diagonal Capture
        $captureOffsets = [-1, 1]; 

        foreach ($captureOffsets as $offsetX) {
            $capX = $fX + $offsetX;
            $capY = $fY + $direction;

            if ($this->isOnBoard($capX, $capY)) {
                $targetPiece = $board[$capY][$capX] ?? null;

                if ($targetPiece !== null && $targetPiece->getColor() !== $this->color) {
                    $moves[] = $this->indicesToPos($capX, $capY);
                }
            }
        }
        return $moves;
    }
    
    public function getLegalMoves(array $board): array {
        $geometricMoves = $this->getGeometricMoves($board);
        return $this->filterSafeMoves($geometricMoves, $board);
    }
}

class Bishop extends AbstractPiece {
    protected $symbol = ['white' => '&#9815;', 'black' => '&#9821;'];

    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }
    
    protected function getGeometricMoves(array $board): array {
        $directions = [[1, 1], [1, -1], [-1, 1], [-1, -1]]; // Diagonal lines
        return $this->calculateSlidingMoves($board, $directions);
    }

    public function getLegalMoves(array $board): array {
        $geometricMoves = $this->getGeometricMoves($board);
        return $this->filterSafeMoves($geometricMoves, $board);
    }
}

class Queen extends AbstractPiece {
    protected $symbol = ['white' => '&#9813;', 'black' => '&#9819;'];
    
    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }
    
    protected function getGeometricMoves(array $board): array {
        // Straight lines
        $straightDirections = [[0, 1], [0, -1], [1, 0], [-1, 0]]; 
        // Diagonal lines
        $diagonalDirections = [[1, 1], [1, -1], [-1, 1], [-1, -1]]; 

        return array_merge(
            $this->calculateSlidingMoves($board, $straightDirections),
            $this->calculateSlidingMoves($board, $diagonalDirections)
        );
    }

    public function getLegalMoves(array $board): array {
        $geometricMoves = $this->getGeometricMoves($board);
        return $this->filterSafeMoves($geometricMoves, $board);
    }
}

class King extends AbstractPiece {
    protected $symbol = ['white' => '&#9812;', 'black' => '&#9818;'];
    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }
    
    protected function getGeometricMoves(array $board): array {
        $moves = [];
        [$fX, $fY] = $this->posToIndices($this->position);
        
        // All 8 surrounding squares
        $directions = [
            [0, 1], [0, -1], [1, 0], [-1, 0], 
            [1, 1], [1, -1], [-1, 1], [-1, -1] 
        ];

        foreach ($directions as [$dX, $dY]) {
            $newX = $fX + $dX;
            $newY = $fY + $dY;
            $targetPos = $this->indicesToPos($newX, $newY);

            if ($this->isOnBoard($newX, $newY)) {
                $targetPiece = $board[$newY][$newX] ?? null;
                
                if ($targetPiece === null || $targetPiece->getColor() !== $this->color) {
                    $moves[] = $targetPos;
                }
            }
        }
        return $moves;
    }
    
    // Castling specific checks
    protected function getCastlingMoves(array $board): array {
        $castlingMoves = [];
        $opponentColor = ($this->color === 'white') ? 'black' : 'white';
        $rank = ($this->color === 'white') ? 0 : 7; // Rank 1 (index 0) or Rank 8 (index 7)

        if ($this->hasMoved) {
            return $castlingMoves; // King has moved, castling is impossible
        }

        // Check if King is currently in check
        if ($this->isSquareAttacked($this->position, $board, $opponentColor)) {
            return $castlingMoves;
        }

        //  King Side Castling (e1/e8 to g1/g8) 
        $rookKS = $board[$rank][7] ?? null; // Rook at h1/h8
        if ($rookKS instanceof Rook && !$rookKS->hasMoved()) {
            if ($board[$rank][5] === null && $board[$rank][6] === null) { // f and g squares empty
                // Check safety of squares f and g
                if (!$this->isSquareAttacked($this->indicesToPos(5, $rank), $board, $opponentColor) &&
                    !$this->isSquareAttacked($this->indicesToPos(6, $rank), $board, $opponentColor)) {
                    $castlingMoves[] = $this->indicesToPos(6, $rank); // Move to g1/g8
                }
            }
        }

        //  Queen Side Castling (e1/e8 to c1/c8) 
        $rookQS = $board[$rank][0] ?? null; // Rook at a1/a8
        if ($rookQS instanceof Rook && !$rookQS->hasMoved()) {
            if ($board[$rank][1] === null && $board[$rank][2] === null && $board[$rank][3] === null) { // b, c, and d squares empty
                // Check safety of squares c and d (King only moves through these)
                if (!$this->isSquareAttacked($this->indicesToPos(3, $rank), $board, $opponentColor) &&
                    !$this->isSquareAttacked($this->indicesToPos(2, $rank), $board, $opponentColor)) {
                    $castlingMoves[] = $this->indicesToPos(2, $rank); // Move to c1/c8
                }
            }
        }

        return $castlingMoves;
    }

    public function getLegalMoves(array $board): array {
        //  Get standard geometric moves (filtered against moving into check)
        $geometricMoves = $this->getGeometricMoves($board);
        $opponentColor = ($this->color === 'white') ? 'black' : 'white';
        $safeGeometricMoves = [];

        foreach ($geometricMoves as $move) {
            if (!$this->isSquareAttacked($move, $board, $opponentColor)) {
                $safeGeometricMoves[] = $move;
            }
        }
        
        //  Add Castling moves
        $castlingMoves = $this->getCastlingMoves($board);
        $finalMoves = array_merge($safeGeometricMoves, $castlingMoves);

        //  Final safety check (for other pieces, but needed here for consistency)
        return $this->filterSafeMoves($finalMoves, $board);
    }
}

class Knight extends AbstractPiece {
    protected $symbol = ['white' => '&#9816;', 'black' => '&#9822;'];
    public function __construct(string $color, string $position) {
        parent::__construct($color, $position);
        $this->symbol = $this->symbol[$color];
    }
    
    protected function getGeometricMoves(array $board): array {
        $moves = [];
        [$fX, $fY] = $this->posToIndices($this->position);
        
        // All 8 'L' offsets
        $offsets = [
            [2, 1], [2, -1], [-2, 1], [-2, -1],
            [1, 2], [1, -2], [-1, 2], [-1, -2]
        ];

        foreach ($offsets as [$dX, $dY]) {
            $newX = $fX + $dX;
            $newY = $fY + $dY;
            $targetPos = $this->indicesToPos($newX, $newY);
            
            if ($this->isOnBoard($newX, $newY)) {
                $targetPiece = $board[$newY][$newX] ?? null;

                if ($targetPiece === null || $targetPiece->getColor() !== $this->color) {
                    $moves[] = $targetPos;
                }
            }
        }
        return $moves;
    }

    public function getLegalMoves(array $board): array {
        $geometricMoves = $this->getGeometricMoves($board);
        return $this->filterSafeMoves($geometricMoves, $board);
    }
}

//  CHESS GAME CLASS 
class ChessGame {
    private array $board; 
    private string $turn; 
    private string $message; 
    private array $history; 

    public function __construct() {
        $this->initializeBoard();
        $this->turn = 'white';
        $this->message = "White to move. Select a piece.";
        $this->history = [];
    }

    private function initializeBoard(): void {
        $this->board = array_fill(0, 8, array_fill(0, 8, null));

        $place = function($piece, $pos) {
            $fileIndex = ord($pos[0]) - ord('a');
            $rankIndex = (int)$pos[1] - 1;
            $this->board[$rankIndex][$fileIndex] = $piece;
        };

        //  FULL STANDARD INITIAL SETUP 

        // Black Pawns (Rank 7)
        foreach (range('a', 'h') as $file) {
            $pos = $file . '7';
            $place(new Pawn('black', $pos), $pos);
        }

        // Black Back Rank (Rank 8)
        $place(new Rook('black', 'a8'), 'a8');
        $place(new Knight('black', 'b8'), 'b8');
        $place(new Bishop('black', 'c8'), 'c8');
        $place(new Queen('black', 'd8'), 'd8');
        $place(new King('black', 'e8'), 'e8');
        $place(new Bishop('black', 'f8'), 'f8');
        $place(new Knight('black', 'g8'), 'g8');
        $place(new Rook('black', 'h8'), 'h8');

        // White Pawns (Rank 2)
        foreach (range('a', 'h') as $file) {
            $pos = $file . '2';
            $place(new Pawn('white', $pos), $pos);
        }

        // White Back Rank (Rank 1)
        $place(new Rook('white', 'a1'), 'a1');
        $place(new Knight('white', 'b1'), 'b1');
        $place(new Bishop('white', 'c1'), 'c1');
        $place(new Queen('white', 'd1'), 'd1');
        $place(new King('white', 'e1'), 'e1');
        $place(new Bishop('white', 'f1'), 'f1');
        $place(new Knight('white', 'g1'), 'g1');
        $place(new Rook('white', 'h1'), 'h1');
    }

    public function setMessage(string $newMessage): void {
        $this->message = $newMessage;
    }
    
    public function makeMove(string $from, string $to): bool {
        $fX = ord($from[0]) - ord('a');
        $fY = (int)$from[1] - 1;
        $tX = ord($to[0]) - ord('a');
        $tY = (int)$to[1] - 1;

        $piece = $this->board[$fY][$fX] ?? null;

        if (!$piece || $piece->getColor() !== $this->turn) {
            $this->setMessage("Error: Invalid selection or not your piece.");
            return false;
        }

        $legalMoves = $piece->getLegalMoves($this->board);

        if (!in_array($to, $legalMoves) || $from === $to) {
            $this->setMessage("Error: Invalid move for " . $piece->getSymbol() . " from $from to $to. (King Safety Check Failed)");
            return false;
        }

        //  CASTLING EXECUTION 
        if ($piece instanceof King && abs($fX - $tX) === 2) {
            $rookFromX = ($tX === 6) ? 7 : 0; // 7 for Kingside (g1/g8), 0 for Queenside (c1/c8)
            $rookToX = ($tX === 6) ? 5 : 3;
            $rookPiece = $this->board[$fY][$rookFromX];
            $rookTo = $this->indicesToPos($rookToX, $fY);

            //  Move the King
            $piece->setPosition($to);
            $this->board[$tY][$tX] = $piece;
            $this->board[$fY][$fX] = null;
            
            //  Move the Rook
            $rookPiece->setPosition($rookTo);
            $this->board[$tY][$rookToX] = $rookPiece;
            $this->board[$fY][$rookFromX] = null;

            $this->history[] = "{$this->turn}: Castled $from to $to";
            $this->turn = ($this->turn === 'white') ? 'black' : 'white';
            $this->setMessage(ucfirst($this->turn) . " to move. Last move: Castling.");
            return true;
        }
        //  END CASTLING EXECUTION 

        // Standard Move Execution
        $piece->setPosition($to);
        $this->board[$tY][$tX] = $piece;
        $this->board[$fY][$fX] = null;

        $this->history[] = "{$this->turn}: {$from} to {$to}";
        $this->turn = ($this->turn === 'white') ? 'black' : 'white';
        $this->setMessage(ucfirst($this->turn) . " to move. Last move: $from to $to.");
        
        return true;
    }

    protected function indicesToPos(int $x, int $y): string {
        return chr(ord('a') + $x) . ($y + 1);
    }

    public function getBoard(): array { return $this->board; }
    public function getTurn(): string { return $this->turn; }
    public function getMessage(): string { return $this->message; }
    public function getHistory(): array { return $this->history; }

    public function getMovesForPiece(string $pos): ?array {
        $fX = ord($pos[0]) - ord('a');
        $fY = (int)$pos[1] - 1;

        $piece = $this->board[$fY][$fX] ?? null;

        if ($piece && $piece->getColor() === $this->turn) {
            return $piece->getLegalMoves($this->board);
        }
        return null;
    }
}

//   HTML RENDERING 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .chessboard-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            grid-template-rows: repeat(8, 1fr);
            width: 100%;
            max-width: 640px; 
            margin: 20px auto;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
            border: 4px solid #1f2937; 
        }
        .square {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 0;
            padding-bottom: 100%; 
            position: relative;
            font-size: 3rem;
            cursor: pointer;
            transition: background-color 0.15s;
        }
        .square-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .light { background-color: #f0d9b5; }
        .dark { background-color: #b58863; }
        .highlight-select {
            box-shadow: inset 0 0 0 4px #4CAF50, 0 0 10px rgba(76, 175, 80, 0.7);
        }
        .highlight-move {
            box-shadow: inset 0 0 0 4px #FFC107, 0 0 10px rgba(255, 193, 7, 0.7);
        }
        .piece-symbol {
            user-select: none;
            line-height: 1;
            font-size: clamp(2rem, 8vw, 4rem); 
            filter: drop-shadow(1px 1px 1px rgba(0,0,0,0.5));
        }
        .white-piece { color: #ffffff; text-shadow: 1px 1px 2px #000000; }
        .black-piece { color: #1f2937; }
        .history-box {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
        }
        .move-button {
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .move-button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="p-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-4 text-gray-800">Group #4 OOP Chess Game</h1>
        <p class="text-center text-sm mb-4 text-gray-600">
            A Fully Funtional Chess Game
        </p>

        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="flex-grow bg-white p-4 rounded-lg shadow-lg">
                <p class="text-xl font-semibold mb-2" id="game-message">
                    Status: <span class="
                        <?php echo $game->getTurn() === 'white' ? 'text-white bg-gray-800 px-2 py-1 rounded' : 'text-gray-800 bg-white px-2 py-1 border border-gray-800 rounded'; ?>
                    ">
                    <?php echo htmlspecialchars($game->getMessage()); ?>
                    </span>
                </p>
                <form method="POST" class="mt-3">
                    <button type="submit" name="reset" class="bg-red-600 text-white move-button shadow-md">
                        &#x21BA; Reset Game
                    </button>
                    <?php if ($selected_piece): ?>
                    <button type="submit" name="cancel" class="bg-gray-400 text-gray-800 move-button shadow-md">
                        &#x2715; Cancel Selection
                    </button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="w-full md:w-1/3 bg-gray-100 p-4 rounded-lg shadow-inner">
                <h3 class="font-bold text-lg mb-2">Move History</h3>
                <div class="history-box text-sm">
                    <?php
                    $history = $game->getHistory();
                    if (empty($history)) {
                        echo "<p class='text-gray-500'>No moves yet.</p>";
                    } else {
                        foreach (array_reverse($history) as $move) {
                            echo "<p>" . htmlspecialchars($move) . "</p>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="chessboard-grid">
            <?php
            $board = $game->getBoard();
            $turn = $game->getTurn();

            // Loop through ranks (rows) from 8 down to 1
            for ($rank = 7; $rank >= 0; $rank--) {
                // Loop through files (columns) from a to h
                for ($file = 0; $file < 8; $file++) {
                    $pos = chr(ord('a') + $file) . ($rank + 1); // e.g., 'a8', 'h1'
                    $piece = $board[$rank][$file];
                    $is_light = ($file + $rank) % 2 == 0;
                    $bg_class = $is_light ? 'light' : 'dark';
                    $piece_color_class = $piece ? ($piece->getColor() === 'white' ? 'white-piece' : 'black-piece') : '';
                    $highlight_class = '';
                    $action_name = '';

                    // Determine the action for the square click/tap
                    if ($selected_piece === $pos) {
                        $highlight_class = 'highlight-select';
                        $action_name = 'cancel'; 
                    } elseif (in_array($pos, $possible_moves)) {
                        $highlight_class = 'highlight-move';
                        $action_name = 'move_to';
                    } elseif ($piece && $piece->getColor() === $turn) {
                        $action_name = 'select_piece';
                    }

                    echo "<div class='square {$bg_class} {$highlight_class}'>";
                    echo "<div class='square-content'>";
                    
                    if ($action_name) {
                        $value = ($action_name === 'select_piece') ? $pos : $pos;
                        echo "<form method='POST' class='h-full w-full flex justify-center items-center'>";
                        echo "<input type='hidden' name='{$action_name}' value='{$value}'>";
                        if ($selected_piece && $action_name === 'move_to') {
                            echo "<input type='hidden' name='selected_piece' value='{$selected_piece}'>";
                        }
                        echo "<button type='submit' class='piece-symbol h-full w-full p-0 flex justify-center items-center {$piece_color_class}'>";
                        echo $piece ? $piece->getSymbol() : ($highlight_class ? '&#x25CF;' : '');
                        echo "</button>";
                        echo "</form>";
                    } else if ($piece) {
                        echo "<span class='piece-symbol {$piece_color_class}'>" . $piece->getSymbol() . "</span>";
                    }
                    echo "</div>";
                    echo "</div>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>