import axios from 'axios';
import {Timeline} from "vis-timeline/peer";
import {DataSet, DataView, Queue} from "vis-data";
import "vis-timeline/styles/vis-timeline-graph2d.css";
window.axios = axios;
window.dataset = DataSet;
window.timeline = Timeline;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
