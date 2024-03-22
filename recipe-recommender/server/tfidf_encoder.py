import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
import pickle 
import numpy as np
from ingredient_parser import ingredient_parser

# load in parsed recipe dataset 
# Read JSON data into DataFrame
recipe_df = pd.read_json('recipes.json', orient='index')
# Remove unnecessary columns
recipe_df = recipe_df.drop(columns=['author_id', 'date_published', 'picture_path'])
# Drop rows with missing values
recipe_df = recipe_df.dropna()
# Reset index and rename index column
recipe_df = recipe_df.reset_index().rename(columns={'index': 'recipe_id'})
# Applying the function to the 'ingredients' column
recipe_df['parsed'] = recipe_df['ingredients'].apply(lambda x: ingredient_parser(x))
# Joining the parsed ingredients lists into strings
recipe_df['parsed'] = recipe_df['parsed'].apply(lambda x: ' '.join(x))

# TF-IDF feature extractor 
tfidf = TfidfVectorizer()
tfidf.fit(recipe_df['parsed'])
tfidf_recipe = tfidf.transform(recipe_df['parsed'])

# save the tfidf model and encodings 
with open("tfidf_encodings.pkl", "wb") as f:
    pickle.dump(tfidf, f)

with open("tfidf.pkl", "wb") as f:
    pickle.dump(tfidf_recipe, f)

    

