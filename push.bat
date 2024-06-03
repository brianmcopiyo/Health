git add .
set /p title="Enter your commit message: "
git commit -m "%title%"
git push origin main
