import { configureStore } from '@reduxjs/toolkit';
import authReducer from './authslice.js';
import loaderReducer from '../Reducer/loadslice.js';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    loader: loaderReducer,
  },
});
