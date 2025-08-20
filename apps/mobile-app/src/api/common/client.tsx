import { Env } from '@env';
import axios from 'axios';

export const client = axios.create({
  baseURL: Env.API_URL,
  timeout: 10000, // 10 second timeout
});
