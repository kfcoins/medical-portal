@echo off
title Mansro Chat WebSocket Server
echo ====================================================
echo Starting the Mansro WebSocket Chat Server...
echo Port: 8081
echo Status: Running
echo ====================================================
echo Leave this window open to keep the chat functionality running.
echo Press Ctrl+C if you need to stop the server.
echo.

php backend\bin\chat-server.php
pause
