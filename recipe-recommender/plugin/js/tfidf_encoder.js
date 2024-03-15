const tf = require('@tensorflow/tfjs-node');
import { ingredient_parser } from './ingredient-parser';

// Get corpus with the documents sorted in alphabetical order
function getAndSortCorpus(data) {
    const corpusSorted = [];
    for (const doc of ingredient_parser(data)) {
        const sortedDoc = doc.sort();
        corpusSorted.push(sortedDoc);
    }
    return corpusSorted;
}

// Calculate average length of each document
function getWindow(corpus) {
    const lengths = corpus.map(doc => doc.length);
    const avgLen = lengths.reduce((total, len) => total + len, 0) / lengths.length;
    return Math.round(avgLen);
}

// Get corpus
const corpus = getAndSortCorpus(recipe_df);
console.log(`Length of corpus: ${corpus.length}`);

// Train and save CBOW Word2Vec model
const model_cbow = new Word2Vec({
    sg: 0,
    workers: 8,
    window: getWindow(corpus),
    minCount: 1,
    vectorSize: 100,
});

model_cbow.train(corpus, (err) => {
    if (err) {
        console.error('Error training Word2Vec model:', err);
    } else {
        model_cbow.save('model_cbow.bin', (err) => {
            if (err) {
                console.error('Error saving Word2Vec model:', err);
            } else {
                console.log('Word2Vec model successfully trained and saved');
            }
        });
    }
});
