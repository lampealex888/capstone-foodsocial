# This markdown provides a step-by-step guide to installing Python, setting up a virtual environment, installing required packages, and running the recipe recommender API in a Linux (Ubuntu) environment

### Installing Python

```bash
sudo install python3
```

### Verify Python Installation

```bash
python3 --version
```

### Setting up a Virtual Environment

1. Install virtualenv if it's not already installed
```bash
pip install virtualenv
```

2. Create the virtual env
```bash
mkdir venv
python3 -m venv .venv
```

3. Activating the virtual env
```bash
source ./venv/bin/activate
```

### Install Required Packages

```bash 
tar -xvzf server.tar.gz
cd server
pip install -r requirements.txt
``` 

NOTE! There are probably going to be errors with installing the dependencies form requirements.txt. If there are any, try to edit requirements.txt to versions that are available that are mentioned in the error message. Or you can try removing it from requirements.txt and see if the program still works later. If it still doesn't work then a different version of Python might be needed (works on 3.8.5, 3.10.12, and 3.11.2)

### Downloading NLTK Data (only required once)

```bash 
python3 # Open the Python IDLE 
import nltk
nltk.download('stopwords')
nltk.download('punkt')
nltk.download('wordnet')
<ctrl+d> # To quit the Python IDLE environment
```

### Running the application with screen

1. Installing screen (if not already installed)
```bash
sudo apt install screen
```
2. Starting named session
```bash
screen -S recipe_recommender
```

3. Activate virtual environment again
```bash
source ../venv/bin/activate
```

3. Starting the server

```bash
python3 app.py
``` 


### Troubleshooting SSL Error
If you encountered SSL errors during package installation, try running the following command and installing essential libraries
```bash
sudo apt install build-essential zlib1g-dev libncurses5-dev libgdbm-dev libnss3-dev libssl-dev libreadline-dev libffi-dev wget```
