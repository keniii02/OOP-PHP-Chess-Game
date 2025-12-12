1. Project Overview
•	Game title: 
 Chess Game 

•	Brief description of the game concept: 
This project is a fully interactive Chess game implemented using Object-Oriented Programming (OOP) principles in PHP. The game simulates real chess movement rules including legal moves, turn-based gameplay, piece interactions, and king safety. The program features a session-based game state so the board updates dynamically as the player interacts via a web interface.
Each chess piece is represented by its own class (King, Queen, Bishop, Knight, Rook, Pawn), inheriting from a shared abstract parent class. The game board, turn logic, move validation, castling rules, and win conditions are handled within a central ChessGame class.

•	Game objectives and win/lose conditions:
The primary objective of chess is to checkmate the opponent’s king, meaning the king is under attack and cannot escape.
In this implementation, the game ends when:
•	A king is captured (basic checkmate simulation).
•	A player has no legal moves (stalemate-like condition).
•	Players may restart the game using the Reset button.
The game follows standard chess rules:
•	Alternate turns (White moves first)
•	Legal movement and capture rules for all pieces
•	King cannot move into check
•	Castling is supported (if legal)
•	Pawns move forward, capture diagonally, 2-square start allowed



2.Technology Stack
•	List all technologies, languages, and frameworks used
PHP
HTML5
CSS

3.Team Members and Contributions
•	Full name of each member: Erika Ambulo 
                            Allyana Espiridion
                            Andre Mamangun
                            Alken Manila

•	Specific contributions of each member to the project

•	Example format: Erika - Documentations
                  Alken - Code Making
                  Allyana - Documentations
                  Andre - Documentations
                           Video

4.How to Play
•	Detailed game rules: 
1. Players take turns selecting a piece and choosing a valid destination.
               2. Movement rules:
                                      Pawn: forward movement, diagonal capture, two-step starting move.
                                      Rook: straight lines (vertical/horizontal).
                                      Bishop: diagonals only.
                                      Knight: L-shaped moves.
                                      Queen: combination of rook + bishop.
                                      King: one square in any direction, cannot move into check.
               3. Castling is allowed if:
                                        King has not moved
                                        Rook has not moved
                                        Path between them is empty
                                        King does not pass through check
                                        
               4. A move is only allowed if it does not leave your own king in check.


•	Controls and instructions
1.	Click a piece to select it.
2.	The game highlights valid moves for the selected piece.
3.	Click a highlighted square to move.
4.	A message display guides the player:

     Invalid moves
     Turn change
     Check-like situations
                      5. Click RESET to start a new game.

•	Game mechanics explanation
1.	The board is internally stored as an 8×8 array.
2.	Every piece class calculates:
                            Its geometric moves
                            Its legal moves
                              3.   Before any move is executed, the game:
                                                                        Simulates the move
                                                                        Checks if the king becomes attacked
                                                                        Only allows moves that keep the king safe
                                 4.    Turn automatically switches after each valid move.
                                 5.    Captures occur when a piece moves into an enemy's square.
5. How to Run the Program (Using Visual Studio Code)
      Step 1 : Install a Local PHP Server
          Our Chess project is written in PHP, so we need a local environment that supports PHP.
      Step 2 : Install Visual Studio Code
           Download and install VS Code
      Step 3 :  Install PHP (only needed if you use the built-in server)
            If you are not using XAMPP/WAMP/MAMP, you must install PHP manually:
•	Download PHP from php.net
•	Add PHP to your system PATH
•	Restart VS Code

Step 4 : Download or Clone the Chess Game Project
      Put the project folder anywhere you like.
      File → Open Folder → Select your Chess folder

•	Required software and dependencies
We only need four things:
 PHP 8.x - Executes the Chess backend
 Visual Studio Code - Edit & run project
 Browser - Plays the game

No database is needed.
No external libraries are required.


