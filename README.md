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
