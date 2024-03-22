from ingredient_parser import ingredient_parser
from gensim.models import Word2Vec
import pandas as pd

# get corpus with the documents sorted in alphabetical order
def get_and_sort_corpus(data):
    corpus_sorted = []
    for doc in data.parsed.values:
        doc.sort()
        corpus_sorted.append(doc)
    return corpus_sorted
  
# calculate average length of each document
def get_window(corpus):
    lengths = [len(doc) for doc in corpus]
    avg_len = float(sum(lengths)) / len(lengths)
    return round(avg_len)
  
# create word2vec embedding
def create_embedding(corpus):
    # get corpus
    # train and save CBOW Word2Vec model
    model_cbow = Word2Vec(
      corpus, sg=0, workers=8, window=get_window(corpus), min_count=1, vector_size=100
    )
    model_cbow.save('model_cbow.bin')
    print("Word2Vec model successfully trained")
    
if __name__ == "__main__":
      # Read JSON data into DataFrame
    recipe_df = pd.read_json('recipes.json', orient='index')
    # Drop rows with missing values
    recipe_df = recipe_df.dropna()
    # Reset index and rename index column
    recipe_df = recipe_df.reset_index().rename(columns={'index': 'recipe_id'})
    # Applying the function to the 'ingredients' column
    recipe_df['parsed'] = recipe_df['ingredients'].apply(lambda x: ingredient_parser(x))
    corpus = get_and_sort_corpus(recipe_df)
    print(f"Length of corpus: {len(corpus)}")
    create_embedding(corpus)